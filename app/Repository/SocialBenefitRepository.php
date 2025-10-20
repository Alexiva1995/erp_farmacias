<?php


namespace App\Repository;

use App\Models\Employee;
use App\Models\ExchangeRate;
use App\Models\Expense;
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
  private function calculateActiveYears($createdAt): int
  {
    $startDate = Carbon::parse($createdAt);
    $currentDate = Carbon::now();
    return $startDate->diffInYears($currentDate);
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
        DB::raw("EXISTS(
                  SELECT 1 FROM users_salary_details usd2 
                  JOIN salary_concepts sc2 ON sc2.id = usd2.salary_concept_id 
                  WHERE usd2.user_id = users.id 
                  AND sc2.name = 'Vacaciones' 
                  AND YEAR(usd2.created_at) = YEAR(NOW())
                ) AS vacation_paid_this_year"),

        DB::raw("EXISTS(
                  SELECT 1 FROM users_salary_details usd3 
                  JOIN salary_concepts sc3 ON sc3.id = usd3.salary_concept_id 
                  WHERE usd3.user_id = users.id 
                  AND sc3.name = 'Bono Vacacional' 
                  AND YEAR(usd3.created_at) = YEAR(NOW())
                ) AS bonus_paid_this_year"),

        DB::raw("EXISTS(
                  SELECT 1 FROM users_salary_details usd4 
                  JOIN salary_concepts sc4 ON sc4.id = usd4.salary_concept_id 
                  WHERE usd4.user_id = users.id 
                  AND sc4.name = 'Utilidades' 
                  AND YEAR(usd4.created_at) = YEAR(NOW())
                ) AS utilities_paid_this_year"),

        // Fechas de último pago
        DB::raw("(
                  SELECT DATE_FORMAT(MAX(usd5.created_at), '%d/%m/%Y')
                  FROM users_salary_details usd5 
                  JOIN salary_concepts sc5 ON sc5.id = usd5.salary_concept_id 
                  WHERE usd5.user_id = users.id 
                  AND sc5.name = 'Vacaciones' 
                  AND YEAR(usd5.created_at) = YEAR(NOW())
                ) AS vacation_last_payment_date"),

        DB::raw("(
                  SELECT DATE_FORMAT(MAX(usd6.created_at), '%d/%m/%Y')
                  FROM users_salary_details usd6 
                  JOIN salary_concepts sc6 ON sc6.id = usd6.salary_concept_id 
                  WHERE usd6.user_id = users.id 
                  AND sc6.name = 'Bono Vacacional' 
                  AND YEAR(usd6.created_at) = YEAR(NOW())
                ) AS bonus_last_payment_date"),

        DB::raw("(
                  SELECT DATE_FORMAT(MAX(usd7.created_at), '%d/%m/%Y')
                  FROM users_salary_details usd7 
                  JOIN salary_concepts sc7 ON sc7.id = usd7.salary_concept_id 
                  WHERE usd7.user_id = users.id 
                  AND sc7.name = 'Utilidades' 
                  AND YEAR(usd7.created_at) = YEAR(NOW())
                ) AS utilities_last_payment_date"),
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

  /**
   * Verificar si ya se pagó un concepto específico en el año actual
   */
  public function hasPaidThisYear(Employee $employee, string $conceptName): bool
  {
    $currentYear = Carbon::now()->year;

    $hasPaid = UsersSalaryDetails::where('user_id', $employee->user_id)
      ->whereHas('concept', function ($query) use ($conceptName) {
        $query->where('name', $conceptName);
      })
      ->whereYear('created_at', $currentYear)
      ->exists();

    Log::info('Repository', [
      'employee_id' => $employee->id,
      'concept_name' => $conceptName,
      'current_year' => $currentYear,
      'has_paid' => $hasPaid
    ]);

    return $hasPaid;
  }

  /**
   * Obtener información de pagos anuales para un empleado
   */
  public function getAnnualPaymentStatus(Employee $employee): array
  {
    $currentYear = Carbon::now()->year;

    $vacationPaid = $this->hasPaidThisYear($employee, 'Vacaciones');
    $bonusPaid = $this->hasPaidThisYear($employee, 'Bono Vacacional');
    $utilitiesPaid = $this->hasPaidThisYear($employee, 'Utilidades');

    return [
      'vacation_paid' => $vacationPaid,
      'bonus_paid' => $bonusPaid,
      'utilities_paid' => $utilitiesPaid,
      'current_year' => $currentYear
    ];
  }

  public function payment(Employee $employee, array $data): bool
  {
    $options = [
      'earnings_voucher' => 'Utilidades',
      'vacation_voucher' => 'Vacaciones',
      'vacation_bonus_voucher' => 'Bono Vacacional',
    ];

    $conceptName = $options[$data['payment']];

    // Verificar si ya se pagó este concepto en el año actual
    if ($this->hasPaidThisYear($employee, $conceptName)) {
      Log::warning('Repository', [
        'message' => "Ya se pagó {$conceptName} para este empleado en el año actual",
        'employee_id' => $employee->id,
        'concept' => $conceptName
      ]);
      throw new \Exception("Ya se pagó {$conceptName} para este empleado en el año actual");
    }

    $concept = SalaryConcept::create([
      'name' => $conceptName,
      'type' => 'salary',
      'frequency' => 'monthly',
    ]);

    $concept = $employee->user->salaries()
      ->create([
        'amount' => $data['amount'],
        'user_id' => $employee->user->id,
        'salary_concept_id' => $concept->id,
      ]);

    Log::info('Repository', [
      'message' => "Pago de {$conceptName} registrado exitosamente",
      'employee_id' => $employee->id,
      'amount' => $data['amount']
    ]);

    return true;
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
    $activeYears = (int) $settlement?->active_years ?? 1;
    $dailyWage = $amount === 0 ? 0 : round($amount / 30);

    Log::info('Repository', ['amount' => $amount, 'activeYears' => $activeYears, 'dailyWage' => $dailyWage]);

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

    $socialBenefitsDays = 30 * $activeYears + 2 * ($activeYears === 0 ? 0 : $activeYears - 1);
    $vacationVoucherDays = 15 * $activeYears + 1 * ($activeYears === 0 ? 0 : $activeYears - 1);
    $vacBonusVoucherDays = $vacationVoucherDays;
    $earningsVoucherDays = 30 * $activeYears;

    Log::info('Repository', [
      'socialBenefitsDays' => $socialBenefitsDays,
      'vacationVoucherDays' => $vacationVoucherDays,
      'earningsVoucherDays' => $earningsVoucherDays
    ]);

    $socialBenefitsAmount = round($socialBenefitsDays * $dailyWage, 2);
    $vacationVoucherAmount = round($vacationVoucherDays * $dailyWage, 2);
    $vacBonusVoucherAmount = round($vacBonusVoucherDays * $dailyWage, 2);
    $earningsVoucherAmount = round($earningsVoucherDays * $dailyWage, 2);

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
}
