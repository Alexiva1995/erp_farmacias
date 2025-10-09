<?php


namespace App\Repository;

use App\Models\Employee;
use App\Models\ExchangeRate;
use App\Models\SalaryConcept;
use DB;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SocialBenefitRepository
{
  public function index(array $data): LengthAwarePaginator
  {
    $search = $data['search'] ?? '';
    $perPage = $data['perPage'] ?? 10;

    return Employee::query()
      ->select([
        'employees.id',
        'employees.name',
        'employees.last_name',
        'employees.identification',
        'users.email',
        'roles.name as role_name',
        DB::raw('TIMESTAMPDIFF(YEAR, employees.created_at, CURDATE()) AS active_years'),

        DB::raw("MAX(CASE
                  WHEN sc.name = 'Salario Base' THEN
                    ROUND(usd.amount / 30, 2) *
                    (15 + IF(TIMESTAMPDIFF(YEAR, employees.created_at, CURDATE()) > 1,
                             TIMESTAMPDIFF(YEAR, employees.created_at, CURDATE()) - 1, 0))
                  ELSE 0
                END) AS vacation_voucher"),

        DB::raw("MAX(CASE
                  WHEN sc.name = 'Salario Base' THEN
                    ROUND(usd.amount / 30, 2) *
                    (15 + IF(TIMESTAMPDIFF(YEAR, employees.created_at, CURDATE()) > 1,
                             TIMESTAMPDIFF(YEAR, employees.created_at, CURDATE()) - 1, 0))
                  ELSE 0
                END) AS vacation_bonus_voucher"),

        DB::raw("MAX(CASE
                  WHEN sc.name = 'Salario Base' THEN
                    ROUND(usd.amount / 30, 2) *
                    TIMESTAMPDIFF(YEAR, employees.created_at, CURDATE())
                  ELSE 0
                END) AS earnings_voucher"),
      ])
      ->leftJoin('users', 'users.id', '=', 'employees.user_id')
      ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
      ->leftJoin('users_salary_details as usd', 'usd.user_id', '=', 'users.id')
      ->leftJoin('salary_concepts as sc', 'sc.id', '=', 'usd.salary_concept_id')
      ->where('employees.is_active', true)
      ->whereNull('employees.deleted_at')
      ->groupBy(
        'employees.id',
        'employees.name',
        'employees.last_name',
        'employees.identification',
        'users.email',
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

    $concept = SalaryConcept::create([
      'name' => $options[$data['payment']],
      'type' => 'salary',
      'frequency' => 'monthly',
    ]);

    $concept = $employee->user->salaries()
      ->create([
        'amount' => $data['amount'],
        'user_id' => $employee->user->id,
        'salary_concept_id' => $concept->id,
      ]);

    return true;
  }

  public function getSettlementData(Employee $employee): array
  {
    $currency = round(ExchangeRate::where('currency_code', 'USD')
      ->whereDate('created_at', now()->today())
      ->value('rate') ?? 0, 2);

    $settlement = Employee::query()
      ->select([
        DB::raw("COALESCE(ROUND((SUM(pd.amount) / 3) * {$currency}, 2), 0) as amount"),
        DB::raw("TIMESTAMPDIFF(YEAR, employees.created_at, CURDATE()) AS active_years"),
      ])
      ->leftJoin('users as u', 'u.id', '=', 'employees.user_id')
      ->leftJoin('users_salary_details as usd', 'usd.user_id', '=', 'u.id')
      ->leftJoin('payslip_details as pd', 'pd.users_salary_details_id', '=', 'usd.id')
      ->leftJoin('salary_concepts as sc', 'sc.id', '=', 'usd.salary_concept_id')
      ->where('employees.id', $employee->id)
      ->where('sc.name', 'Salario Base')
      ->groupBy(['employees.id', 'employees.created_at'])
      ->orderByDesc('pd.created_at')
      ->limit(6)
      ->first();

    $amount = round((float) $settlement?->amount ?? 0, 2);
    $activeYears = (int) $settlement?->active_years ?? 1;
    $dailyWage = $amount === 0 ? 0 : round($amount / 30);

    $sub = DB::table('employees')
      ->leftJoin('users as u', 'u.id', '=', 'employees.user_id')
      ->leftJoin('users_salary_details as usd', 'usd.user_id', '=', 'u.id')
      ->leftJoin('payslip_details as pd', 'pd.users_salary_details_id', '=', 'usd.id')
      ->leftJoin('payslips as ps', 'ps.id', '=', 'pd.payslip_id')
      ->leftJoin('salary_concepts as sc', 'sc.id', '=', 'usd.salary_concept_id')
      ->leftJoin(
        DB::raw('( SELECT DATE(created_at) AS rate_date, rate
                    FROM exchange_rates
                    WHERE currency_code = \'USD\'
                    ORDER BY created_at DESC ) AS er'),
        'er.rate_date',
        '=',
        DB::raw('DATE(ps.payslip_date)')
      )
      ->where('employees.id', $employee->id)
      ->whereIn('sc.name', ['Vacaciones', 'Bono Vacacional', 'Utilidades'])
      ->groupBy(['sc.name', 'ps.id', 'pd.amount'])
      ->select(
        'sc.name as concept_name',
        DB::raw('pd.amount * er.rate AS amount_usd')
      );

    $deductions = DB::query()
      ->fromSub($sub, 'x')
      ->selectRaw('
        COALESCE(ROUND(SUM(CASE WHEN concept_name = ? THEN amount_usd ELSE 0 END), 2), 0) AS vacation_voucher,
        COALESCE(ROUND(SUM(CASE WHEN concept_name = ? THEN amount_usd ELSE 0 END), 2), 0) AS vacation_bonus_voucher,
        COALESCE(ROUND(SUM(CASE WHEN concept_name = ? THEN amount_usd ELSE 0 END), 2), 0) AS earnings_voucher
    ', ['Vacaciones', 'Bono Vacacional', 'Utilidades'])
      ->first();

    $socialBenefitsDays = 30 * $activeYears + 2 * ($activeYears - 1);
    $vacationVoucherDays = 15 * $activeYears + 1 * ($activeYears - 1);
    $vacBonusVoucherDays = $vacationVoucherDays;
    $earningsVoucherDays = 30 * $activeYears;

    $socialBenefitsAmount = round($socialBenefitsDays * $dailyWage, 2);
    $vacationVoucherAmount = round($vacationVoucherDays * $dailyWage, 2);
    $vacBonusVoucherAmount = round($vacBonusVoucherDays * $dailyWage, 2);
    $earningsVoucherAmount = round($earningsVoucherDays * $dailyWage, 2);

    $totalSettlementAmount = round($socialBenefitsAmount
      + $vacationVoucherAmount
      + $vacBonusVoucherAmount
      + $earningsVoucherAmount, 2);

    $totalDeductions = round((float) $deductions->vacation_voucher
      + (float) $deductions->vacation_bonus_voucher
      + (float) $deductions->earnings_voucher, 2);

    $totalSettlementUsd = round($totalSettlementAmount / $currency, 2);
    $totalDeductionsUsd = round($totalDeductions / $currency, 2);
    $finalUsd = round($totalSettlementUsd - $totalDeductionsUsd, 2);

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
    ];
  }

  public function fire(Employee $employee, array $data): bool
  {
    $employee->update(['is_active' => false]);

    $settlement = $this->getSettlementData($employee);

    $percentage = (float) ($data['percentage'] ?? 100);
    $total = round((float) $data['total'], 2);

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

    return true;
  }
}
