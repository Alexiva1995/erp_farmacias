<?php


namespace App\Repository;

use App\Models\Employee;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\SalaryConcept;
use App\Models\Transaction;
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
    $currency = round(ExchangeRate::orderByDesc('created_at')
      ->where('currency_code', 'BS')
      ->value('rate') ?? 1, 2);
    \Log::info('Repository', ['currency' => $currency]);

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
        DB::raw("TIMESTAMPDIFF(YEAR, employees.created_at, CURDATE()) AS active_years")
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

    \Log::info('Repository', ['settlement' => $settlement]);
    $amount = round((float) $settlement?->amount ?? 0, 2);
    $activeYears = (int) $settlement?->active_years ?? 1;
    $dailyWage = $amount === 0 ? 0 : round($amount / 30);

    \Log::info('Repository', ['amount' => $amount, 'activeYears' => $activeYears, 'dailyWage' => $dailyWage]);

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
              AND DATE(created_at) = DATE(ps.payslip_date)
            ORDER BY created_at DESC
            LIMIT 1
        ) AS amount_usd')
      );
    \Log::info('Repository', context: ['sub-query' => $sub]);

    $deductions = DB::query()
      ->fromSub($sub, 'x')
      ->selectRaw('
        COALESCE(ROUND(SUM(CASE WHEN concept_name = ? THEN amount_usd ELSE 0 END), 2), 0) AS vacation_voucher,
        COALESCE(ROUND(SUM(CASE WHEN concept_name = ? THEN amount_usd ELSE 0 END), 2), 0) AS vacation_bonus_voucher,
        COALESCE(ROUND(SUM(CASE WHEN concept_name = ? THEN amount_usd ELSE 0 END), 2), 0) AS earnings_voucher
    ', ['Vacaciones', 'Bono Vacacional', 'Utilidades'])
      ->first();

    \Log::info('Repository', ['deductions' => $deductions]);

    $socialBenefitsDays = 30 * $activeYears + 2 * ($activeYears === 0 ? 0 : $activeYears - 1);
    $vacationVoucherDays = 15 * $activeYears + 1 * ($activeYears === 0 ? 0 : $activeYears - 1);
    $vacBonusVoucherDays = $vacationVoucherDays;
    $earningsVoucherDays = 30 * $activeYears;

    \Log::info('Repository', [
      'socialBenefitsDays' => $socialBenefitsDays,
      'vacationVoucherDays' => $vacationVoucherDays,
      'earningsVoucherDays' => $earningsVoucherDays
    ]);

    $socialBenefitsAmount = round($socialBenefitsDays * $dailyWage, 2);
    $vacationVoucherAmount = round($vacationVoucherDays * $dailyWage, 2);
    $vacBonusVoucherAmount = round($vacBonusVoucherDays * $dailyWage, 2);
    $earningsVoucherAmount = round($earningsVoucherDays * $dailyWage, 2);

    \Log::info('Repository', [
      'socialBenefitsAmount' => $socialBenefitsAmount,
      'vacationVoucherAmount' => $vacationVoucherAmount,
      'vacBonusVoucherAmount' => $vacBonusVoucherAmount,
      'earningsVoucherAmount' => $earningsVoucherAmount
    ]);

    $totalSettlementAmount = round($socialBenefitsAmount
      + $vacationVoucherAmount
      + $vacBonusVoucherAmount
      + $earningsVoucherAmount, 2);

    \Log::info('Repository', [
      'totalSettlementAmount' => $totalSettlementAmount,
    ]);

    $totalDeductions = round((float) $deductions->vacation_voucher
      + (float) $deductions->vacation_bonus_voucher
      + (float) $deductions->earnings_voucher, 2);

    \Log::info('Repository', [
      'totalDeductions' => $totalDeductions,
    ]);

    $totalSettlementUsd = round($totalSettlementAmount / $currency, 2);
    $totalDeductionsUsd = round($totalDeductions / $currency, 2);
    $finalUsd = round($totalSettlementUsd - $totalDeductionsUsd, 2);
    $startDate = $employee->created_at->format('d/m/Y');

    \Log::info('Repository', [
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
    $employee->update(['is_active' => false]);

    $settlement = $this->getSettlementData($employee);

    $percentage = (float) ($data['percentage'] ?? 100);
    $total = round((float) $data['total'], 2);

    $cop_exchange_rate = ExchangeRate::orderByDesc('created_at')
      ->where('currency_code', 'COP')
      ->first();

    $bs_exchange_rate = ExchangeRate::orderByDesc('created_at')
      ->where('currency_code', 'BS')
      ->first();

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

    return true;
  }
}
