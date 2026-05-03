<?php

namespace App\Repository;

use App\Models\Employee;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\Payslip;
use App\Models\PayslipDetails;
use App\Models\Transaction;
use App\Models\UsersSalaryDetails;
use Artisan;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Collection;

class PayslipRepository
{
  public function index(array $data)
  {
    $cop_rate = \App\Models\ExchangeRate::where('currency_code', 'COP')->orderByDesc('created_at')->value('rate') ?? 0;

    $query = Payslip::with('details.salary.concept')
      ->select('payslips.*')
      ->addSelect(DB::raw("(
          SELECT ROUND(SUM(pd.amount * {$cop_rate}), 2)
          FROM payslip_details pd
          WHERE pd.payslip_id = payslips.id
          AND pd.amount > 0
      ) as total_full_cop"))
      ->orderByDesc('id');

    if (isset($data['startDate']) && $data['startDate']) {
      $query->whereDate('payslip_date', '>=', $data['startDate']);
    }

    if (isset($data['endDate']) && $data['endDate']) {
      $query->whereDate('payslip_date', '<=', $data['endDate']);
    }

    return $query->paginate($data['perPage']);
  }

  public function generate(Carbon $date, string $name, Collection $details): bool
  {
    $exchange_rate = ExchangeRate::orderByDesc('created_at')
      ->where('currency_code', '=', 'BS')
      ->first();

    if (!isset($exchange_rate)) {
      $exitCode = Artisan::call("app:update-exchange-rate");

      if ($exitCode === 0) {
        $exchange_rate = ExchangeRate::orderByDesc('created_at')
          ->where('currency_code', '=', 'BS')
          ->first();

      } else {
        \Log::error("Failed to fetch exchange rate");
        throw new \Exception("No se pudo guardar la tasa del día BS");
      }
    }


    $payslip = Payslip::create([
      'payslip_date' => $date->format('Y-m-d'),
      'name' => $name,
      'total' => 0,
      'exchange_rate' => $exchange_rate->rate
    ]);

    foreach ($details as $detail) {
      PayslipDetails::create([
        'payslip_id' => $payslip->id,
        'users_salary_details_id' => $detail->id,
        'amount' => $detail->amount,
      ]);
    }

    $payslip->update(['total' => $payslip->details()->where('amount', '>', 0)->sum('amount')]);

    return true;
  }

  public function getEligibleSalaryDetails(Carbon $date): Collection
  {
    $this->ensureSalaryConceptsExist();

    $startOfMonth = $date->copy()->startOfMonth()->format('Y-m-d');
    $endOfMonth   = $date->copy()->endOfMonth()->format('Y-m-d');

    // Si ya existe nómina este mes → es la 2da (paga conceptos variables)
    $hasPreviousInMonth = Payslip::whereBetween('payslip_date', [$startOfMonth, $endOfMonth])->exists();
    $isSecondNomina     = $hasPreviousInMonth || $date->day > 15;

    $employees = Employee::where('is_active', true)->with('user.salaries.concept')->get();
    $details   = collect();

    foreach ($employees as $employee) {
      if (!$employee->user) continue;

      $salaries     = $employee->user->salaries;
      $baseSalary   = (float)($salaries->where('concept.name', 'Salario Básico Mensual')->first()?->amount ?? 40.00);
      $foodVoucher  = (float)($salaries->where('concept.name', 'Bono de Alimentación')->first()?->amount ?? 40.00);
      $package      = (float)($employee->total_package_usd ?? 0);

      // ── 1. Salario Base (50% por quincena) ──────────────────────────────────
      $salarioQuincena = round($baseSalary / 2, 2);
      $details->push($this->createTempDetail($employee, 'Salario Básico Mensual', $salarioQuincena));

      if ($isSecondNomina) {
        // ── 2. Deducciones Legales (solo en 2da nómina, sobre salario base completo) ─
        $details->push($this->createTempDetail($employee, 'IVSS (4%)',                  -round($baseSalary * 0.04,  2)));
        $details->push($this->createTempDetail($employee, 'RPE - Paro Forzoso (0.5%)', -round($baseSalary * 0.005, 2)));
        $details->push($this->createTempDetail($employee, 'FAOV (1%)',                  -round($baseSalary * 0.01,  2)));

        // ── 3. Bono de Alimentación ─────────────────────────────────────────
        $details->push($this->createTempDetail($employee, 'Bono de Alimentación', round($foodVoucher, 2)));

        // ── 4. Asistencia Social de Salud (basada en consumo real de farmacia) ─
        $consumoFarmacia    = $this->getTotalConsumoFarmacia($employee, $date->month, $date->year);
        $saldoDeudaAnterior = (float)($employee->saldo_deuda ?? 0);
        $consumoTotal       = $consumoFarmacia + $saldoDeudaAnterior;

        // Espacio disponible dentro del paquete (después de base + alimentación)
        $disponibleParaVariable = max(0, $package - $baseSalary - $foodVoucher);

        // Salud: lo que efectivamente se puede pagar sin exceder el paquete
        $saludPagado = round(min($consumoTotal, $disponibleParaVariable), 2);
        $details->push($this->createTempDetail($employee, 'Asistencia Social de Salud (Art. 105 LOTTT)', $saludPagado));

        // ── 5. Bono Extraordinario (sobrante del paquete tras salud) ───────────
        $restanteParaBono    = $disponibleParaVariable - $saludPagado;
        $bonusExtraordinario = max(0, round($restanteParaBono, 2));
        $details->push($this->createTempDetail($employee, 'Bono Extraordinario de Rendimiento', $bonusExtraordinario));

        // ── 6. Actualizar remanente (saldo_deuda) del empleado ─────────────────
        $nuevoSaldoDeuda = ($restanteParaBono >= 0)
          ? 0.0
          : round(abs($consumoTotal - $disponibleParaVariable), 2);

        $employee->update(['saldo_deuda' => $nuevoSaldoDeuda]);
      }
    }

    return $details;
  }

  /**
   * Calcular consumo total del empleado en la farmacia como cliente (por cédula) en el mes dado.
   */
  private function getTotalConsumoFarmacia(Employee $employee, int $month, int $year): float
  {
    $identification = $employee->identification;
    $ordersTotal = 0.0;
    
    if ($identification) {
      $client = \App\Models\Client::where('identification', $identification)->first();
      if ($client) {
        $ordersTotal = (float) \App\Models\Order::where('client_id', $client->id)
          ->whereMonth('order_date', $month)
          ->whereYear('order_date', $year)
          ->where(function ($q) {
            $q->where('status', 'Completed')->orWhereNotNull('completed_at');
          })
          ->sum('total_amount_usd');
      }
    }

    // Sumar consumo manual/crédito si existe
    $manualConsumption = (float) \DB::table('employee_health_consumption')
      ->where('employee_id', $employee->id)
      ->where('month', $month)
      ->where('year', $year)
      ->value('amount') ?? 0.0;

    return round($ordersTotal + $manualConsumption, 2);
  }

  private function createTempDetail(Employee $employee, string $conceptName, float $amount): object
  {
    $concept = \App\Models\SalaryConcept::where('name', $conceptName)->first();
    
    if (!$concept) {
        throw new \Exception("Concepto de nómina no encontrado: {$conceptName}");
    }

    $salaryDetail = $employee->user->salaries()->firstOrCreate(
      ['salary_concept_id' => $concept->id],
      ['amount' => 0]
    );

    return (object)[
      'id' => $salaryDetail->id,
      'amount' => $amount,
      'name' => $conceptName
    ];
  }

  public function updateDetails(Payslip $payslip, array $details): bool
  {
    if (empty($details['vouchers'])) {
      return false;
    }

    $cases = [];
    $ids = [];
    foreach ($details['vouchers'] as $v) {
      $id = (int) $v['id'];
      $amount = (float) $v['amount_usd'];
      $cases[$id] = "WHEN {$id} THEN {$amount}";
      $ids[] = $id;
    }

    $idList = implode(',', $ids);
    $caseSql = implode(' ', $cases);

    $sql = "UPDATE payslip_details
        SET amount = CASE id {$caseSql} END,
            updated_at = NOW()
        WHERE id IN ({$idList})
        AND payslip_id = ?";
    DB::statement($sql, [$payslip->id]);

    DB::statement(
      'UPDATE payslips
         SET total = (
               SELECT COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0)
               FROM payslip_details
               WHERE payslip_id = payslips.id
             ),
             updated_at = NOW()
         WHERE id = ?',
      [$payslip->id]
    );

    return true;
  }

  public function finalize(Payslip $payslip, array $data)
  {
    $currency = $data['currency'];
    $count = $data['count'];
    $total = $data['payed'];

    $cop_exchange_rate = ExchangeRate::orderByDesc('created_at')
      ->where('currency_code', 'COP')
      ->first();

    $bs_exchange_rate = ExchangeRate::orderByDesc('created_at')
      ->where('currency_code', 'BS')
      ->first();

    $total_bs = round($payslip->total * $bs_exchange_rate->rate, 2);

    Expense::create([
      'name' => 'Nómina',
      'category_id' => 1,
      'amount' => $total,
      'amount_usd' => $payslip->total,
      'amount_bs' => $total_bs,
      'currency' => $currency,
      'expense_date' => now(),
      'user_id' => auth()->user()->id,
      'count' => $count,
      'is_deductible' => true,
      'type_of_expense' => 'Normal'
    ]);

    $type = match ($count) {
      'Efectivo' => 'CASH',
      'Tarjeta' => 'CARD',
      'Pago móvil' => 'MOBILE',
      'Transferencia' => 'TRANSFER',
      'Binance' => 'BINANCE',
      'Paypal' => 'PAYPAL'
    };

    $exchange_rate_id = $currency === 'BS'
      ? $bs_exchange_rate->id
      : ($currency === 'COP'
        ? $cop_exchange_rate->id
        : null);

    Transaction::create([
      'user_id' => auth()->user()->id,
      'category_id' => 1,
      'exchange_rate_id' => $exchange_rate_id,
      'description' => 'Pago de nómina',
      'currency' => $currency,
      'type' => $type,
      'amount' => $total,
      'movement_type' => 'OUT',
      'transaction_date' => now()
    ]);
    return $payslip->update(['status' => 1, 'payed' => $total, 'currency' => $currency]);
  }

  public function exportableData(Payslip $payslip, string $type)
  {
    $cop_rate = \App\Models\ExchangeRate::where('currency_code', 'COP')->orderByDesc('created_at')->value('rate') ?? 0;
    $currency = $type === 'full' ? $cop_rate : $payslip->exchange_rate;
    $now = now();
    $month = (int) $now->format('n');
    $isDec = $month === 12;

    $select = [
      DB::raw('@row := @row + 1 as id'),
      'employees.id          as employee_id',
      DB::raw('COALESCE(employees.name, users.username) as name'),
      'employees.last_name',
      'employees.identification',
      'roles.name            as role',
      'employees.total_package_usd',
      DB::raw((int) $isDec . '  as is_december'),
      DB::raw("MAX(TIMESTAMPDIFF(YEAR, employees.created_at, '{$payslip->payslip_date}')) AS active_years"),
    ];

    $add = function (array $cols) use (&$select) {
      $select = array_merge($select, $cols);
    };

    $add([
      DB::raw("
      CASE WHEN MAX(TIMESTAMPDIFF(YEAR, employees.created_at, CURDATE())) > 1 THEN
      ROUND(MAX(CASE WHEN sc.name = 'Vacaciones'      THEN pd.amount * {$currency} ELSE 0 END), 2)
      ELSE 0 END
      AS vacation_voucher"),
      DB::raw("
       CASE WHEN MAX(TIMESTAMPDIFF(YEAR, employees.created_at, CURDATE())) > 1 THEN
      ROUND(MAX(CASE WHEN sc.name = 'Bono Vacacional' THEN pd.amount * {$currency} ELSE 0 END), 2)
      ELSE 0 END
      AS vacation_bonus_voucher"),
    ]);

    if ($isDec) {
      $add([
        DB::raw("ROUND(MAX(CASE WHEN sc.name = 'Utilidades' THEN pd.amount * {$currency} ELSE 0 END), 2) AS earnings_voucher"),
      ]);
    }

    $add([
      DB::raw("SUM(CASE WHEN sc.name = 'Bono de Alimentación'        THEN pd.amount * {$currency} ELSE 0 END) AS food_voucher"),
      DB::raw("SUM(CASE WHEN sc.name = 'Bono de Transporte'           THEN pd.amount * {$currency} ELSE 0 END) AS transportation_voucher"),
      DB::raw("SUM(CASE WHEN sc.name = 'Bono Extraordinario de Rendimiento' THEN pd.amount * {$currency} ELSE 0 END) AS performance_voucher"),
      DB::raw("SUM(CASE WHEN sc.name = 'Asistencia Social de Salud (Art. 105 LOTTT)' THEN pd.amount * {$currency} ELSE 0 END) AS health_support_voucher"),
      DB::raw("SUM(CASE WHEN sc.name = 'Salario Básico Mensual'       THEN usd.amount * {$currency} ELSE 0 END) AS base_salary_voucher"),
      DB::raw("SUM(CASE WHEN sc.name = 'Bono de Facturas'             THEN pd.amount * {$currency} ELSE 0 END) AS invoice_voucher"),
      DB::raw("SUM(CASE WHEN sc.name = 'Bono de Ventas'               THEN pd.amount * {$currency} ELSE 0 END) AS sales_voucher"),
      DB::raw("SUM(CASE WHEN sc.name = 'Bono de Ayuda familiar'       THEN pd.amount * {$currency} ELSE 0 END) AS family_support_voucher"),
      DB::raw("SUM(CASE WHEN sc.name = 'Bono de Productos Asignados'  THEN pd.amount * {$currency} ELSE 0 END) AS assigned_products_voucher"),
      DB::raw("SUM(CASE WHEN sc.name = 'Bono de Crecimiento de Ventas' THEN pd.amount * {$currency} ELSE 0 END) AS sales_growth_voucher"),
      DB::raw("SUM(CASE WHEN sc.name = 'Salario Básico Mensual'       THEN pd.amount * {$currency} ELSE 0 END) AS salary_to_pay_voucher"),
      DB::raw("SUM(CASE WHEN sc.name = 'IVSS (4%)'                    THEN pd.amount * {$currency} ELSE 0 END) AS social_security_voucher"),
      DB::raw("SUM(CASE WHEN sc.name = 'Prestamos'                    THEN pd.amount * {$currency} ELSE 0 END) AS loans_voucher"),
      DB::raw("SUM(CASE WHEN sc.name = 'Dias no trabajados'           THEN pd.amount * {$currency} ELSE 0 END) AS days_not_worked_voucher"),
      DB::raw("SUM(CASE WHEN sc.name = 'Liquidacion'                  THEN pd.amount * {$currency} ELSE 0 END) AS settlement_voucher"),
      DB::raw("SUM(CASE WHEN sc.name = 'RPE - Paro Forzoso (0.5%)'     THEN pd.amount * {$currency} ELSE 0 END) AS employment_voucher"),
      DB::raw("SUM(CASE WHEN sc.name = 'FAOV (1%)'                    THEN pd.amount * {$currency} ELSE 0 END) AS housing_property_benefits_voucher"),
    ]);
    DB::statement('SET @row := 0');

    return Employee::query()
      ->select($select)
      ->join('users', 'users.id', '=', 'employees.user_id')
      ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
      ->leftJoin('users_salary_details AS usd', 'usd.user_id', '=', 'users.id')
      ->leftJoin('salary_concepts AS sc', 'sc.id', '=', 'usd.salary_concept_id')
      ->leftJoin('payslip_details AS pd', function($join) use ($payslip) {
          $join->on('pd.users_salary_details_id', '=', 'usd.id')
               ->where('pd.payslip_id', '=', $payslip->id);
      })
      ->where('employees.is_active', 1)
      ->whereExists(function ($query) use ($payslip) {
          $query->select(DB::raw(1))
                ->from('payslip_details')
                ->join('users_salary_details', 'users_salary_details.id', '=', 'payslip_details.users_salary_details_id')
                ->whereColumn('users_salary_details.user_id', 'users.id')
                ->where('payslip_details.payslip_id', $payslip->id);
      })
      ->groupBy(
        'employees.id',
        'employees.name',
        'employees.last_name',
        'employees.identification',
        'roles.name',
      )
      ->orderBy('employees.name');
  }

  public function getData(Payslip $payslip, string $type): array
  {
    $date = Carbon::parse($payslip->payslip_date);
    $period = $date->day <= 15 
        ? "01/{$date->format('m/Y')} hasta el 15/{$date->format('m/Y')}"
        : "16/{$date->format('m/Y')} hasta el {$date->endOfMonth()->format('d/m/Y')}";

    return [
      'items' => $this->exportableData($payslip, $type)->get()->toArray(),
      'name' => $payslip->name,
      'date' => $payslip->payslip_date,
      'status' => $payslip->status,
      'period' => $period,
      'exchange_rate' => $type === 'full' ? (\App\Models\ExchangeRate::where('currency_code', 'COP')->orderByDesc('created_at')->value('rate') ?? 0) : $payslip->exchange_rate,
      'currency_code' => $type === 'full' ? 'COP' : 'Bs.'
    ];
  }

  public function getEmployeeVouchers(Payslip $payslip, Employee $employee): array
  {
    $this->ensureSalaryConceptsExist();
    $currency = $this->todayUsdRate();

    $query = Payslip::query()
      ->selectRaw(
        "
        sc.name as name,
        sc.type as type,
        sc.frequency as frequency,
        pd.amount as amount_usd,
        ROUND(pd.amount * {$currency}, 2) as amount_bs,
        pd.id as id"
      )
      ->leftJoin('payslip_details AS pd', 'pd.payslip_id', '=', 'payslips.id')
      ->leftJoin('users_salary_details AS usd', 'usd.id', '=', 'pd.users_salary_details_id')
      ->leftJoin('salary_concepts AS sc', 'sc.id', '=', 'usd.salary_concept_id')
      ->leftJoin('users', 'users.id', '=', 'usd.user_id')
      ->leftJoin('employees', 'employees.user_id', '=', 'users.id')
      ->where('payslips.id', $payslip->id)
      ->where('employees.id', $employee->id)
      ->whereIn('sc.name', [
        'Bono de Alimentación', 
        'Salario Básico Mensual', 
        'Asistencia Social de Salud (Art. 105 LOTTT)',
        'Bono Extraordinario de Rendimiento',
        'IVSS (4%)',
        'RPE - Paro Forzoso (0.5%)',
        'FAOV (1%)'
      ])
      ->get()
      ->toArray();

    return ['currency' => $currency, 'results' => $query];
  }

  private function todayUsdRate(): float
  {
    $rate = ExchangeRate::where('currency_code', 'BS')
      ->whereDate('created_at', now()->today())
      ->value('rate');

    if ($rate !== null) {
      return (float) $rate;
    }

    $exitCode = Artisan::call('app:update-exchange-rate');

    if ($exitCode !== 0) {
      \Log::error('Failed to fetch exchange rate');
      throw new \RuntimeException('No se pudo obtener la tasa del día USD');
    }

    $rate = ExchangeRate::where('currency_code', 'USD')
      ->whereDate('created_at', now()->today())
      ->value('rate');

    if ($rate === null) {
      throw new \RuntimeException('No se econtró una tasa para usar');
    }

    return (float) $rate;
  }

  /**
   * Garantiza que los conceptos básicos de nómina existan en la base de datos.
   */
  private function ensureSalaryConceptsExist(): void
  {
    $concepts = [
      'Salario Básico Mensual' => ['type' => 'salary', 'frequency' => 'fortnight'],
      'Bono de Alimentación' => ['type' => 'salary', 'frequency' => 'monthly'],
      'Asistencia Social de Salud (Art. 105 LOTTT)' => ['type' => 'salary', 'frequency' => 'monthly'],
      'Bono Extraordinario de Rendimiento' => ['type' => 'salary', 'frequency' => 'monthly'],
      'IVSS (4%)' => ['type' => 'deduction', 'frequency' => 'fortnight'],
      'RPE - Paro Forzoso (0.5%)' => ['type' => 'deduction', 'frequency' => 'fortnight'],
      'FAOV (1%)' => ['type' => 'deduction', 'frequency' => 'fortnight']
    ];

    foreach ($concepts as $name => $data) {
      \App\Models\SalaryConcept::updateOrCreate(['name' => $name], $data);
    }
  }
}
