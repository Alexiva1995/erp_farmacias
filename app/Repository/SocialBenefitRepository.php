<?php


namespace App\Repository;

use App\Models\Employee;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\Payslip;
use App\Models\PayslipDetails;
use App\Models\SalaryConcept;
use App\Models\Transaction;
use App\Models\UsersSalaryDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

class SocialBenefitRepository
{
  /**
   * Calcular años de antigüedad usando Carbon (compatible cross-platform)
   */
  private function calculateActiveYears($createdAt): float
  {
    $startDate = Carbon::parse($createdAt);
    $currentDate = Carbon::now();
    return $startDate->diffInYears($currentDate, true); // true para obtener valor decimal
  }

  /**
   * Obtener fecha actual formateada para MySQL (compatible cross-platform)
   */
  private function getCurrentDateForMySQL(): string
  {
    return Carbon::now()->format('Y-m-d');
  }

  public function index(array $data): LengthAwarePaginator
  {
    $search = $data['search'] ?? '';
    $perPage = $data['perPage'] ?? 10;
    $currentDate = $this->getCurrentDateForMySQL();

    return Employee::query()
      ->select([
        'employees.id',
        'employees.name',
        'employees.last_name',
        'employees.identification',
        'users.email',
        'roles.name as role_name',
        'employees.created_at',
        DB::raw("DATEDIFF('{$currentDate}', employees.created_at) / 365.25 AS active_years"),

        DB::raw("MAX(CASE
                  WHEN sc.name = 'Salario Base' THEN
                    ROUND(usd.amount / 30, 2) *
                    (15 + IF(DATEDIFF('{$currentDate}', employees.created_at) / 365.25 > 1,
                             FLOOR(DATEDIFF('{$currentDate}', employees.created_at) / 365.25) - 1, 0))
                  ELSE 0
                END) AS vacation_voucher"),

        DB::raw("MAX(CASE
                  WHEN sc.name = 'Salario Base' THEN
                    ROUND(usd.amount / 30, 2) *
                    (15 + IF(DATEDIFF('{$currentDate}', employees.created_at) / 365.25 > 1,
                             FLOOR(DATEDIFF('{$currentDate}', employees.created_at) / 365.25) - 1, 0))
                  ELSE 0
                END) AS vacation_bonus_voucher"),

        DB::raw("MAX(CASE
                  WHEN sc.name = 'Salario Base' THEN
                    ROUND(usd.amount / 30, 2) *
                    FLOOR(DATEDIFF('{$currentDate}', employees.created_at) / 365.25)
                  ELSE 0
                END) AS earnings_voucher"),

        // Información de pagos anuales
      ])
      ->leftJoin('users', 'users.id', '=', 'employees.user_id')
      ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
      ->leftJoin('users_salary_details as usd', 'usd.user_id', '=', 'users.id')
      ->leftJoin('salary_concepts as sc', 'sc.id', '=', 'usd.salary_concept_id')
      ->where('employees.is_active', true)
      ->whereNull('employees.deleted_at')
      ->where(function ($query) use ($search) {
        $query->where('employees.name', 'LIKE', "%{$search}%")
          ->orWhere('employees.last_name', 'LIKE', "%{$search}%")
          ->orWhere('employees.identification', 'LIKE', "%{$search}%")
          ->orWhere('users.email', 'LIKE', "%{$search}%");
      })
      ->groupBy(
        'employees.id',
        'employees.name',
        'employees.last_name',
        'employees.identification',
        'users.email',
        'users.id',
        'roles.name',
        'employees.created_at'
      )
      ->paginate($perPage);
  }

  public function payment(Employee $employee, array $data): bool
  {
    $options = [
      'earnings_voucher' => 'Utilidades',
      'vacation_voucher' => 'Vacaciones',
      'vacation_bonus_voucher' => 'Bono Vacacional',
    ];

    $conceptName = $options[$data['payment']];

    $concept = SalaryConcept::create([
      'name' => $conceptName,
      'type' => 'salary',
      'frequency' => 'monthly',
    ]);

    $salaryDetail = $employee->user->salaries()
      ->create([
        'amount' => $data['amount'],
        'user_id' => $employee->user->id,
        'salary_concept_id' => $concept->id,
      ]);

    // Generar automáticamente el payslip_detail para que aparezca en deducciones
    $this->generatePayslipDetailForPayment($salaryDetail, $data['amount']);

    Log::info('Repository', [
      'message' => "Pago de {$conceptName} registrado exitosamente",
      'employee_id' => $employee->id,
      'amount' => $data['amount']
    ]);

    return true;
  }

  /**
   * Generar payslip_detail automáticamente para pagos individuales
   */
  private function generatePayslipDetailForPayment($salaryDetail, $amount): void
  {
    try {
      // Buscar o crear un payslip para la fecha actual
      $payslip = Payslip::firstOrCreate(
        [
          'payslip_date' => Carbon::now()->format('Y-m-d'),
          'name' => 'Pago Individual - ' . Carbon::now()->format('Y-m-d'),
        ],
        [
          'payslip_date' => Carbon::now()->format('Y-m-d'),
          'name' => 'Pago Individual - ' . Carbon::now()->format('Y-m-d'),
          'total' => 0, // Se calculará después
          'status' => 1, // 1 = generado
        ]
      );

      // Crear el payslip_detail
      PayslipDetails::create([
        'payslip_id' => $payslip->id,
        'users_salary_details_id' => $salaryDetail->id,
        'amount' => $amount,
      ]);

      // Actualizar el total del payslip
      $totalAmount = PayslipDetails::where('payslip_id', $payslip->id)->sum('amount');
      $payslip->update(['total' => $totalAmount]);

      Log::info('Repository', [
        'message' => 'Payslip detail generado automáticamente',
        'payslip_id' => $payslip->id,
        'salary_detail_id' => $salaryDetail->id,
        'amount' => $amount
      ]);
    } catch (\Exception $e) {
      Log::error('Repository', [
        'message' => 'Error generando payslip detail automáticamente',
        'error' => $e->getMessage(),
        'salary_detail_id' => $salaryDetail->id
      ]);
    }
  }

  public function getSettlementData(Employee $employee): array
  {
    $currency = round(ExchangeRate::orderByDesc('created_at')
      ->where('currency_code', 'BS')
      ->value('rate') ?? 1, 2);
    Log::info('Repository', ['currency' => $currency]);

    // Calcular años de antigüedad usando Carbon (compatible cross-platform)
    $activeYears = $this->calculateActiveYears($employee->created_at);
    $currentDate = $this->getCurrentDateForMySQL();

    $settlement = Employee::query()
      ->select([
        DB::raw("COALESCE(ROUND(
        SUM(pd.amount) / 
            CASE COUNT(pd.id)
              WHEN 6 THEN 3
              WHEN 5 THEN 2.5
              WHEN 4 THEN 2
              WHEN 3 THEN 1.5
              WHEN 2 THEN 1
              ELSE 1
            END
        * {$currency}, 2), 0) as amount"),
        DB::raw("DATEDIFF('{$currentDate}', employees.created_at) / 365.25 AS active_years")
      ])
      ->leftJoin('users as u', 'u.id', '=', 'employees.user_id')
      ->leftJoin('users_salary_details as usd', 'usd.user_id', '=', 'u.id')
      ->leftJoin('payslip_details as pd', 'pd.users_salary_details_id', '=', 'usd.id')
      ->leftJoin('salary_concepts as sc', 'sc.id', '=', 'usd.salary_concept_id')
      ->where('employees.id', $employee->id)
      ->where('sc.name', 'Salario Base')
      ->groupBy(['employees.id', 'employees.created_at'])
      ->orderByDesc(DB::raw('MAX(pd.created_at)'))
      ->limit(6)
      ->first();

    Log::info('Repository', ['settlement' => $settlement]);
    $amount = round((float) $settlement?->amount ?? 0, 2);
    // Usar el cálculo de Carbon en lugar del SQL que puede fallar
    // $activeYears = (int) $settlement?->active_years ?? 1;

    // Calcular salario promedio usando los últimos 6 salarios en Bolívares
    $averageSalaryData = $this->calculateAverageSalaryForBenefits($employee);
    Log::info('Repository', ['average_salary_data' => $averageSalaryData]);

    // Usar el salario promedio si está disponible, sino usar el amount calculado
    $baseSalary = $averageSalaryData['average_salary'] > 0 ? $averageSalaryData['average_salary'] : $amount;
    $dailyWage = $baseSalary === 0 ? 0 : round($baseSalary / 30);

    // Calcular salario integral según fórmula: Salario Diario + [(30 × Salario Diario) ÷ 360] + [(15 × Salario Diario) ÷ 360]
    $integralSalary = $dailyWage + (30 * $dailyWage / 360) + (15 * $dailyWage / 360);

    Log::info('Repository', [
      'amount' => $amount,
      'activeYears' => $activeYears,
      'dailyWage' => $dailyWage,
      'baseSalary' => $baseSalary,
      'integralSalary' => $integralSalary,
      'averageSalaryData' => $averageSalaryData
    ]);

    $sub = DB::table('employees')
      ->leftJoin('users as u', 'u.id', '=', 'employees.user_id')
      ->leftJoin('users_salary_details as usd', 'usd.user_id', '=', 'u.id')
      ->leftJoin('payslip_details as pd', 'pd.users_salary_details_id', '=', 'usd.id')
      ->leftJoin('payslips as ps', 'ps.id', '=', 'pd.payslip_id')
      ->leftJoin('salary_concepts as sc', 'sc.id', '=', 'usd.salary_concept_id')
      ->where('employees.id', $employee->id)
      ->whereIn('sc.name', ['Vacaciones', 'Bono Vacacional', 'Utilidades'])
      ->select(
        'sc.name as concept_name',
        DB::raw('pd.amount * (
            SELECT rate
            FROM exchange_rates
            WHERE currency_code = \'USD\'
              AND DATE_FORMAT(created_at, \'%Y-%m-%d\') = DATE_FORMAT(ps.payslip_date, \'%Y-%m-%d\')
            ORDER BY created_at DESC
            LIMIT 1
        ) AS amount_usd')
      );
    Log::info('Repository', ['sub-query' => $sub]);

    $deductions = DB::query()
      ->fromSub($sub, 'x')
      ->selectRaw('
        COALESCE(ROUND(SUM(CASE WHEN concept_name = ? THEN amount_usd ELSE 0 END), 2), 0) AS vacation_voucher,
        COALESCE(ROUND(SUM(CASE WHEN concept_name = ? THEN amount_usd ELSE 0 END), 2), 0) AS vacation_bonus_voucher,
        COALESCE(ROUND(SUM(CASE WHEN concept_name = ? THEN amount_usd ELSE 0 END), 2), 0) AS earnings_voucher
    ', ['Vacaciones', 'Bono Vacacional', 'Utilidades'])
      ->first();

    Log::info('Repository', ['deductions' => $deductions]);

    // NUEVAS FÓRMULAS SEGÚN CORRECCIONES DEL JEFE

    // Calcular meses de antigüedad para regla especial de prestaciones sociales
    $monthsOfService = $employee->created_at->diffInMonths(Carbon::now());

    // PRESTACIONES SOCIALES: Regla especial para menos de 6 meses
    if ($monthsOfService < 6) {
      // Si tiene menos de 6 meses: 5 días por cada mes completado
      $socialBenefitsDays = round($monthsOfService * 5); // Redondear a entero
    } else {
      // Si tiene 6+ meses: 30 días completos
      $socialBenefitsDays = 30;
    }

    // Bonificación por años completados: +2 días por cada año completo adicional
    $completedYears = floor($activeYears);
    if ($completedYears >= 2) {
      $bonusDays = ($completedYears - 1) * 2; // -1 porque el primer año ya está incluido en los 30 días
      $socialBenefitsDays += $bonusDays;
    }

    // VACACIONES: Fórmula base con fracciones + bonificación por años completados
    $vacationVoucherDays = round(15 * $activeYears); // Redondear a entero
    // Bonificación: +1 día por cada año completo adicional
    if ($completedYears >= 2) {
      $vacationBonusDays = ($completedYears - 1) * 1; // -1 porque el primer año ya está incluido
      $vacationVoucherDays += $vacationBonusDays;
    }

    // BONO VACACIONAL: Igual que vacaciones
    $vacBonusVoucherDays = $vacationVoucherDays;

    // UTILIDADES: Fórmula base con fracciones (sin bonificación)
    $earningsVoucherDays = round(30 * $activeYears); // Redondear a entero

    Log::info('Repository', [
      'socialBenefitsDays' => $socialBenefitsDays,
      'vacationVoucherDays' => $vacationVoucherDays,
      'earningsVoucherDays' => $earningsVoucherDays
    ]);

    // Usar salario integral para cálculos de prestaciones sociales
    $socialBenefitsAmount = round($socialBenefitsDays * $integralSalary, 2);
    $vacationVoucherAmount = round($vacationVoucherDays * $integralSalary, 2);
    $vacBonusVoucherAmount = round($vacBonusVoucherDays * $integralSalary, 2);
    $earningsVoucherAmount = round($earningsVoucherDays * $integralSalary, 2);

    Log::info('Repository', [
      'socialBenefitsAmount' => $socialBenefitsAmount,
      'vacationVoucherAmount' => $vacationVoucherAmount,
      'vacBonusVoucherAmount' => $vacBonusVoucherAmount,
      'earningsVoucherAmount' => $earningsVoucherAmount
    ]);

    $totalSettlementAmount = round($socialBenefitsAmount
      + $vacationVoucherAmount
      + $vacBonusVoucherAmount
      + $earningsVoucherAmount, 2);

    Log::info('Repository', [
      'totalSettlementAmount' => $totalSettlementAmount,
    ]);

    $totalDeductions = round((float) $deductions->vacation_voucher
      + (float) $deductions->vacation_bonus_voucher
      + (float) $deductions->earnings_voucher, 2);

    Log::info('Repository', [
      'totalDeductions' => $totalDeductions,
    ]);

    $totalSettlementUsd = round($totalSettlementAmount / $currency, 2);
    $totalDeductionsUsd = round($totalDeductions / $currency, 2);

    // Prevenir montos negativos - si las deducciones son mayores que el total, usar 0
    $finalUsd = max(0, round($totalSettlementUsd - $totalDeductionsUsd, 2));
    $startDate = $employee->created_at->format('d/m/Y');

    Log::info('Repository', [
      'totalSettlementUsd' => $totalSettlementUsd,
      'totalDeductionsUsd' => $totalDeductionsUsd,
      'finalUsd' => $finalUsd,
      'startDate' => $startDate,
      'employee resignation date' => $employee->resignation?->effective_date,
    ]);

    return [
      'amount' => $amount,
      'active_years' => $activeYears,
      'currency' => $currency,
      'daily_wage' => $dailyWage,
      'integral_salary' => round($integralSalary, 2),
      'social_benefits_days' => $socialBenefitsDays,
      'social_benefits_amount' => $socialBenefitsAmount,
      'vacation_voucher_days' => $vacationVoucherDays,
      'vacation_voucher_amount' => $vacationVoucherAmount,
      'vacation_bonus_voucher_days' => $vacBonusVoucherDays,
      'vacation_bonus_voucher_amount' => $vacBonusVoucherAmount,
      'earnings_voucher_days' => $earningsVoucherDays,
      'earnings_voucher_amount' => $earningsVoucherAmount,
      'total_settlement_days' => $socialBenefitsDays + $vacationVoucherDays + $vacBonusVoucherDays + $earningsVoucherDays,
      'total_settlement_amount' => $totalSettlementAmount,
      'total_settlement_usd' => $totalSettlementUsd,
      'vacation_voucher_deduction' => (float) $deductions->vacation_voucher,
      'vacation_bonus_voucher_deduction' => (float) $deductions->vacation_bonus_voucher,
      'earnings_voucher_deduction' => (float) $deductions->earnings_voucher,
      'total_deductions' => $totalDeductions,
      'total_deductions_usd' => $totalDeductionsUsd,
      'final_usd' => $finalUsd,
      'resignation_date' => $employee->resignation?->effective_date,
      'starting_date' => $startDate,
      'base_salary' => $baseSalary,
      'average_salary' => $averageSalaryData['average_salary'],
      'average_salary_count' => $averageSalaryData['salaries_count'],
      'last_salaries' => $averageSalaryData['last_salaries'],
      'calculation_details' => $averageSalaryData['calculation_details'],
    ];
  }

  public function fire(Employee $employee, array $data): bool
  {
    try {
      // Verificar que el empleado esté activo
      if (!$employee->is_active) {
        Log::error('Repository', ['error' => 'Employee is already inactive', 'employee_id' => $employee->id]);
        throw new \Exception('El empleado ya está inactivo');
      }

      // Actualizar estado del empleado
      $employee->update(['is_active' => false]);
      Log::info('Repository', ['employee_deactivated' => $employee->id]);

      $settlement = $this->getSettlementData($employee);

      $percentage = (float) ($data['percentage'] ?? 100);
      $total = round((float) $data['total'], 2);

      // Verificar que existan las tasas de cambio
      $cop_exchange_rate = ExchangeRate::orderByDesc('created_at')
        ->where('currency_code', 'COP')
        ->first();

      $bs_exchange_rate = ExchangeRate::orderByDesc('created_at')
        ->where('currency_code', 'BS')
        ->first();

      if (!$bs_exchange_rate) {
        Log::error('Repository', ['error' => 'BS exchange rate not found']);
        throw new \Exception('No se encontró la tasa de cambio BS');
      }

      $total_bs = round($total * $bs_exchange_rate->rate, 2);
      $currency = $data['currency'];
      $count = $data['count'];
      $payed = $data['payed'];

      Expense::create([
        'name' => "Despido de empleado ID: {$employee->id}",
        'category_id' => 1,
        'amount' => $payed,
        'amount_usd' => abs($total),
        'amount_bs' => abs($total_bs),
        'currency' => $currency,
        'expense_date' => now(),
        'user_id' => Auth::user()?->id ?? 1,
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
        'user_id' => Auth::user()?->id ?? 1,
        'category_id' => 1,
        'exchange_rate_id' => $exchange_rate_id,
        'description' => "Despido de empleado ID: {$employee->id}",
        'currency' => $currency,
        'type' => $type,
        'amount' => $payed,
        'movement_type' => 'OUT',
        'transaction_date' => now()
      ]);

      $employee->settlement()->create([
        'currency' => $settlement['currency'],
        'social_benefits_days' => $settlement['social_benefits_days'],
        'social_benefits_amount' => $settlement['social_benefits_amount'],
        'vacation_voucher_days' => $settlement['vacation_voucher_days'],
        'vacation_voucher_amount' => $settlement['vacation_voucher_amount'],
        'vacation_bonus_voucher_days' => $settlement['vacation_bonus_voucher_days'],
        'vacation_bonus_voucher_amount' => $settlement['vacation_bonus_voucher_amount'],
        'earnings_voucher_days' => $settlement['earnings_voucher_days'],
        'earnings_voucher_amount' => $settlement['earnings_voucher_amount'],
        'total_settlement' => $settlement['total_settlement_amount'],
        'vacation_voucher_deduction' => $settlement['vacation_voucher_deduction'],
        'vacation_bonus_voucher_deduction' => $settlement['vacation_bonus_voucher_deduction'],
        'earnings_voucher_deduction' => $settlement['earnings_voucher_deduction'],
        'total_deduction' => $settlement['total_deductions'],
        'subtotal' => $settlement['final_usd'],
        'percentage' => $percentage,
        'total' => $total,
      ]);

      Log::info('Repository', ['employee_fired_successfully' => $employee->id]);
      return true;
    } catch (\Exception $e) {
      Log::error('Repository', [
        'error' => $e->getMessage(),
        'employee_id' => $employee->id,
        'data' => $data
      ]);

      // Revertir el cambio de estado si falló el proceso
      $employee->update(['is_active' => true]);

      throw $e;
    }
  }

  /**
   * Obtener los últimos salarios de un empleado en Bolívares con detección automática de moneda
   */
  public function getEmployeeLastSalariesInBs(Employee $employee, int $count = 6): array
  {
    $currentDate = $this->getCurrentDateForMySQL();

    // Primero intentar obtener salarios con conversión USD->Bs
    $salariesWithUsdConversion = DB::table('employees')
      ->select([
        'pd.amount as amount_original',
        'ps.payslip_date',
        'er.rate as exchange_rate',
        DB::raw("pd.amount * er.rate AS amount_bs_calculated"),
        DB::raw("'USD' as detected_currency")
      ])
      ->leftJoin('users as u', 'u.id', '=', 'employees.user_id')
      ->leftJoin('users_salary_details as usd', 'usd.user_id', '=', 'u.id')
      ->leftJoin('payslip_details as pd', 'pd.users_salary_details_id', '=', 'usd.id')
      ->leftJoin('payslips as ps', 'ps.id', '=', 'pd.payslip_id')
      ->leftJoin('salary_concepts as sc', 'sc.id', '=', 'usd.salary_concept_id')
      ->leftJoin('exchange_rates as er', function ($join) {
        $join->on(DB::raw("DATE_FORMAT(er.created_at, '%Y-%m-%d')"), '=', DB::raw("DATE_FORMAT(ps.payslip_date, '%Y-%m-%d')"))
          ->where('er.currency_code', '=', 'USD');
      })
      ->where('employees.id', $employee->id)
      ->where('sc.name', 'Salario Base')
      ->whereNotNull('pd.amount')
      ->whereNotNull('er.rate')
      ->orderByDesc('ps.payslip_date')
      ->limit($count)
      ->get();

    // Si no hay salarios con conversión USD, obtener salarios directos en Bs
    if ($salariesWithUsdConversion->isEmpty()) {
      $salariesDirectBs = DB::table('employees')
        ->select([
          'pd.amount as amount_original',
          'ps.payslip_date',
          DB::raw('1 as exchange_rate'),
          DB::raw('pd.amount AS amount_bs_calculated'),
          DB::raw("'BS' as detected_currency")
        ])
        ->leftJoin('users as u', 'u.id', '=', 'employees.user_id')
        ->leftJoin('users_salary_details as usd', 'usd.user_id', '=', 'u.id')
        ->leftJoin('payslip_details as pd', 'pd.users_salary_details_id', '=', 'usd.id')
        ->leftJoin('payslips as ps', 'ps.id', '=', 'pd.payslip_id')
        ->leftJoin('salary_concepts as sc', 'sc.id', '=', 'usd.salary_concept_id')
        ->where('employees.id', $employee->id)
        ->where('sc.name', 'Salario Base')
        ->whereNotNull('pd.amount')
        ->orderByDesc('ps.payslip_date')
        ->limit($count)
        ->get();

      $salaries = $salariesDirectBs;
    } else {
      $salaries = $salariesWithUsdConversion;
    }

    // Detectar automáticamente la moneda basada en los montos
    if ($salaries->isNotEmpty()) {
      $avgAmount = $salaries->avg('amount_original');
      $hasUsdRates = $salaries->where('exchange_rate', '>', 1)->isNotEmpty();

      // Si los montos son muy bajos (< 50) y hay tasas USD, probablemente están en USD
      if ($avgAmount < 50 && $hasUsdRates) {
        // Mantener conversión USD->Bs
        Log::info('Repository', [
          'message' => 'Salarios detectados como USD, aplicando conversión',
          'employee_id' => $employee->id,
          'avg_amount' => $avgAmount,
          'has_usd_rates' => $hasUsdRates
        ]);
      } else {
        // Los montos son altos o no hay tasas USD, probablemente están en Bs
        $salaries = $salaries->map(function ($salary) {
          return (object) [
            'amount_original' => $salary->amount_original,
            'payslip_date' => $salary->payslip_date,
            'exchange_rate' => 1,
            'amount_bs_calculated' => $salary->amount_original,
            'detected_currency' => 'BS'
          ];
        });

        Log::info('Repository', [
          'message' => 'Salarios detectados como Bs, usando montos directos',
          'employee_id' => $employee->id,
          'avg_amount' => $avgAmount,
          'has_usd_rates' => $hasUsdRates
        ]);
      }
    }

    return $salaries->map(function ($salary) {
      return [
        'amount_bs' => round($salary->amount_bs_calculated, 2),
        'amount_original' => round($salary->amount_original, 2),
        'exchange_rate' => $salary->exchange_rate,
        'payslip_date' => $salary->payslip_date,
        'detected_currency' => $salary->detected_currency
      ];
    })->toArray();
  }

  /**
   * Calcular el salario promedio para prestaciones sociales
   */
  public function calculateAverageSalaryForBenefits(Employee $employee): array
  {
    $lastSalaries = $this->getEmployeeLastSalariesInBs($employee, 6);

    if (empty($lastSalaries)) {
      return [
        'average_salary' => 0,
        'salaries_count' => 0,
        'last_salaries' => [],
        'calculation_details' => 'No se encontraron salarios registrados'
      ];
    }

    $salariesCount = count($lastSalaries);
    $totalAmount = array_sum(array_column($lastSalaries, 'amount_bs'));
    $averageQuincenal = $totalAmount / $salariesCount;
    $averageMonthly = $averageQuincenal * 2; // Convertir de quincenal a mensual

    return [
      'average_salary' => round($averageMonthly, 2),
      'salaries_count' => $salariesCount,
      'last_salaries' => $lastSalaries,
      'calculation_details' => "Promedio de {$salariesCount} salarios: " .
        number_format($totalAmount, 2) . " Bs. ÷ {$salariesCount} = " .
        number_format($averageQuincenal, 2) . " Bs. × 2 = " .
        number_format($averageMonthly, 2) . " Bs."
    ];
  }
}
