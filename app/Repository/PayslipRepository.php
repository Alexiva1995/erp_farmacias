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
    return Payslip::with('details.salary.concept')
      ->orderByDesc('id')
      ->paginate($data['perPage']);
  }

  public function generate(Carbon $date, string $name, Collection $details): bool
  {
    $exchange_rate = ExchangeRate::where('created_at', '=', $date->format('Y-m-d'))
      ->where('currency_code', '=', 'BS')
      ->first();

    if (!isset($exchange_rate)) {
      $exitCode = Artisan::call("app:update-exchange-rate");

      if ($exitCode === 0) {
        $exchange_rate = ExchangeRate::where("currency_code", "BS")
          ->whereDate("created_at", Carbon::today())
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

    $payslip->update(['total' => $payslip->details()->sum('amount')]);

    return true;
  }

  public function getEligibleSalaryDetails()
  {
    return UsersSalaryDetails::query()
      ->join('salary_concepts as c', 'c.id', '=', 'users_salary_details.salary_concept_id')
      ->join('employees as e', 'e.user_id', '=', 'users_salary_details.user_id')
      ->where('e.is_active', true)
      ->select('users_salary_details.id', 'users_salary_details.amount')
      ->get();
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
               SELECT COALESCE(SUM(amount), 0)
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
    return $payslip->update(['status' => 1]);
  }

  public function exportableData(Payslip $payslip, string $type)
  {
    $currency = $type === 'full' ? 1 : $payslip->exchange_rate;
    $now = now();
    $month = (int) $now->format('n');
    $isDec = $month === 12;

    $select = [
      DB::raw('@row := @row + 1 as id'),
      'employees.id          as employee_id',
      'employees.name',
      'employees.last_name',
      'employees.identification',
      'roles.name            as role',
      DB::raw((int) $isDec . '  as is_december'),
      DB::raw('MAX(TIMESTAMPDIFF(YEAR, employees.created_at, CURDATE())) AS active_years'),
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
      DB::raw("ROUND(MAX(CASE WHEN sc.name = 'Bono de Alimentación'        THEN pd.amount * {$currency} ELSE 0 END), 2) AS food_voucher"),
      DB::raw("ROUND(MAX(CASE WHEN sc.name = 'Bono de Transporte'           THEN pd.amount * {$currency} ELSE 0 END), 2) AS transportation_voucher"),
      DB::raw("ROUND(MAX(CASE WHEN sc.name = 'Bono de Rendimiento'          THEN pd.amount * {$currency} ELSE 0 END), 2) AS performance_voucher"),
      DB::raw("ROUND(MAX(CASE WHEN sc.name = 'Salario Base'                 THEN pd.amount * {$currency} ELSE 0 END), 2) AS base_salary_voucher"),
      DB::raw("ROUND(MAX(CASE WHEN sc.name = 'Bono de Facturas'                 THEN pd.amount * {$currency} ELSE 0 END), 2) AS invoice_voucher"),
      DB::raw("ROUND(MAX(CASE WHEN sc.name = 'Bono de Ventas'                 THEN pd.amount * {$currency} ELSE 0 END), 2) AS sales_voucher"),
      DB::raw("ROUND(MAX(CASE WHEN sc.name = 'Bono de Ayuda familiar'                 THEN pd.amount * {$currency} ELSE 0 END), 2) AS family_support_voucher"),
      DB::raw("ROUND(MAX(CASE WHEN sc.name = 'Bono de Productos Asignados'                 THEN pd.amount * {$currency} ELSE 0 END), 2) AS assigned_products_voucher"),
      DB::raw("ROUND(MAX(CASE WHEN sc.name = 'Bono de Crecimiento de Ventas'                 THEN pd.amount * {$currency} ELSE 0 END), 2) AS sales_growth_voucher"),
      DB::raw("ROUND(MAX(CASE WHEN sc.name = 'Salario Base'                 THEN ROUND((pd.amount * {$currency}) / 2, 2) ELSE 0 END), 2) AS salary_to_pay_voucher"),
      DB::raw("ROUND(MAX(CASE WHEN sc.name = 'Seguro Social'                THEN pd.amount * {$currency} ELSE 0 END), 2) AS social_security_voucher"),
      DB::raw("ROUND(MAX(CASE WHEN sc.name = 'Prestamos'                    THEN pd.amount * {$currency} ELSE 0 END), 2) AS loans_voucher"),
      DB::raw("ROUND(MAX(CASE WHEN sc.name = 'Dias no trabajados'                    THEN pd.amount * {$currency} ELSE 0 END), 2) AS days_not_worked_voucher"),
      DB::raw("ROUND(MAX(CASE WHEN sc.name = 'Liquidacion'                    THEN pd.amount * {$currency} ELSE 0 END), 2) AS settlement_voucher"),
      DB::raw("ROUND(MAX(CASE WHEN sc.name = 'Prestacional de Empleo'                    THEN pd.amount * {$currency} ELSE 0 END), 2) AS employment_voucher"),
      DB::raw("ROUND(MAX(CASE WHEN sc.name = 'Prestación Vivienda y Hacienda'                    THEN pd.amount * {$currency} ELSE 0 END), 2) AS housing_property_benefits_voucher"),
    ]);
    DB::statement('SET @row := 0');

    return Payslip::query()
      ->select($select)
      ->leftJoin('payslip_details AS pd', 'pd.payslip_id', '=', 'payslips.id')
      ->leftJoin('users_salary_details AS usd', 'usd.id', '=', 'pd.users_salary_details_id')
      ->leftJoin('salary_concepts AS sc', 'sc.id', '=', 'usd.salary_concept_id')
      ->leftJoin('users', 'users.id', '=', 'usd.user_id')
      ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
      ->leftJoin('employees', 'employees.user_id', '=', 'users.id')
      ->where('payslips.id', $payslip->id)
      ->where('employees.is_active', 1)
      ->groupBy(
        'employees.id',
        'employees.name',
        'employees.last_name',
        'employees.identification',
        'roles.name',
      )
      ->orderBy('id');
  }

  public function getData(Payslip $payslip, string $type): array
  {
    $end_period = Carbon::createFromFormat('Y-m-d', $payslip->payslip_date);
    $start_period = $end_period->subWeeks(2)->format('d/m/Y');
    $end_period = $end_period->addWeeks(2)->format('d/m/Y');
    $period = "{$start_period} hasta el {$end_period}";

    return [
      'items' => $this->exportableData($payslip, $type)->get()->toArray(),
      'name' => $payslip->name,
      'date' => $payslip->payslip_date,
      'status' => $payslip->status,
      'period' => $period
    ];
  }

  public function getEmployeeVouchers(Payslip $payslip, Employee $employee): array
  {
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
      ->whereIn('sc.name', ['Bono de Alimentación', 'Salario Base'])
      ->get()
      ->toArray();

    return ['currency' => $currency, 'results' => $query];
  }

  private function todayUsdRate(): float
  {
    $rate = ExchangeRate::where('currency_code', 'USD')
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
}
