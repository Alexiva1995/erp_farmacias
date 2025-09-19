<?php

namespace App\Repository;

use App\Models\Employee;
use App\Models\ExchangeRate;
use App\Models\Payslip;
use App\Models\PayslipDetails;
use App\Models\UsersSalaryDetails;
use Artisan;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Collection;

class PayslipRepository
{
  public function index(array $data)
  {
    return Payslip::with('details.salary.concept')->paginate($data['perPage']);
  }

  public function generate(Carbon $date, string $name, Collection $details): bool
  {
    $payslip = Payslip::create([
      'payslip_date' => $date->format('Y-m-d'),
      'name' => $name,
      'total' => 0,
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
      ->whereIn('c.name', ['Bono de Alimentación', 'Salario Base'])
      ->select('users_salary_details.id', 'users_salary_details.amount')
      ->get();
  }

  public function updateDetails(Payslip $payslip, array $details): bool
  {
    if (empty($details['vouchers'])) {
      return false;
    }

    /* 1.  Build CASE string */
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

    /* 2.  Update only the rows that belong to this payslip */
    $sql = "UPDATE payslip_details
        SET amount = CASE id {$caseSql} END,
            updated_at = NOW()
        WHERE id IN ({$idList})
        AND payslip_id = ?";
    \Log::info($sql, [$payslip->id]);   // tail storage/logs/laravel.log
    DB::statement($sql, [$payslip->id]);

    /* 3.  Refresh the payslip total in the same query */
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

  public function finalize(Payslip $payslip)
  {
    return $payslip->update(['status' => 1]);
  }

  public function exportableData(Payslip $payslip)
  {
    $currency = $this->todayUsdRate();

    DB::statement('SET @row := 0');

    $query = Payslip::query()
      ->selectRaw('@row := @row + 1 as id')
      ->selectRaw(
        "employees.id          as employee_id,
                 employees.name,
                 employees.last_name,
                 employees.identification,
                 roles.name as role,
        ROUND(MAX(CASE WHEN sc.name = 'Bono de Alimentación' THEN pd.amount * {$currency} ELSE 0 END), 2) AS food_voucher,
        ROUND(MAX(CASE WHEN sc.name = 'Bono de Transporte' THEN pd.amount * {$currency} ELSE 0 END), 2) AS transportation_voucher,
        ROUND(MAX(CASE WHEN sc.name = 'Bono de Rendimiento' THEN pd.amount * {$currency} ELSE 0 END), 2) AS performance_voucher,
        ROUND(MAX(CASE WHEN sc.name = 'Bono de Facturas' THEN pd.amount * {$currency} ELSE 0 END), 2) AS invoice_voucher,
        ROUND(MAX(CASE WHEN sc.name = 'Bono de Ventas' THEN pd.amount * {$currency} ELSE 0 END), 2) AS sales_voucher,
        ROUND(MAX(CASE WHEN sc.name = 'Bono de Crecimiento de Ventas' THEN pd.amount * {$currency} ELSE 0 END), 2) AS sales_growth_voucher,
        ROUND(MAX(CASE WHEN sc.name = 'Bono de Productos Asignados' THEN pd.amount * {$currency} ELSE 0 END), 2) AS assigned_products_voucher,
        ROUND(MAX(CASE WHEN sc.name = 'Salario Base' THEN pd.amount * {$currency} ELSE 0 END), 2) AS base_salary_voucher,
        ROUND(MAX(CASE WHEN sc.name = 'Salario Base' THEN pd.amount * {$currency} ELSE 0 END) / 2, 2) AS salary_to_pay_voucher,
        ROUND(MAX(CASE WHEN sc.name = 'Utilidades' THEN pd.amount * {$currency} ELSE 0 END), 2) AS earnings_voucher,
        ROUND(MAX(CASE WHEN sc.name = 'Vacaciones' THEN pd.amount * {$currency} ELSE 0 END), 2) AS vacation_voucher,
        ROUND(MAX(CASE WHEN sc.name = 'Bono Vacacional' THEN pd.amount * {$currency} ELSE 0 END), 2) AS vacation_bonus_voucher,
        ROUND(MAX(CASE WHEN sc.name = 'Liquidación' THEN pd.amount * {$currency} ELSE 0 END), 2) AS settlement_voucher,
        ROUND(MAX(CASE WHEN sc.name = 'Bono de Ayuda familiar' THEN pd.amount * {$currency} ELSE 0 END), 2) AS family_support_voucher,
        ROUND(MAX(CASE WHEN sc.name = 'Seguro Social' THEN pd.amount * {$currency} ELSE 0 END), 2) AS social_security_voucher,
        ROUND(MAX(CASE WHEN sc.name = 'Prestacional de Empleo' THEN pd.amount * {$currency} ELSE 0 END), 2) AS employment_voucher,
        ROUND(MAX(CASE WHEN sc.name = 'Prestación Vivienda y Hacienda' THEN pd.amount * {$currency} ELSE 0 END), 2) AS housing_property_benefits_voucher,
        ROUND(MAX(CASE WHEN sc.name = 'Dias no Trabajados' THEN pd.amount * {$currency} ELSE 0 END), 2) AS days_not_worked_voucher,
        ROUND(MAX(CASE WHEN sc.name = 'Prestamos' THEN pd.amount * {$currency} ELSE 0 END), 2) AS loans_voucher,

        /* ---  BONOS  --------------------------------------------- */
        ROUND(
              MAX(CASE WHEN sc.name = 'Bono de Alimentación'        THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Bono de Transporte'           THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Bono de Rendimiento'          THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Bono de Facturas'              THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Bono de Ventas'                THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Bono de Crecimiento de Ventas' THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Bono de Productos Asignados'   THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Salario Base'                  THEN pd.amount * {$currency} ELSE 0 END) / 2
            + MAX(CASE WHEN sc.name = 'Utilidades'                    THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Vacaciones'                    THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Bono Vacacional'               THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Bono de Ayuda familiar'        THEN pd.amount * {$currency} ELSE 0 END)
        , 2) AS positive_vouchers,

        /* ---  Deducciones  --------------------------------------------- */
        ROUND(
              MAX(CASE WHEN sc.name = 'Liquidación'                   THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Seguro Social'                 THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Prestacional de Empleo'        THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Prestación Vivienda y Hacienda' THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Dias no Trabajados'            THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Prestamos'                     THEN pd.amount * {$currency} ELSE 0 END)
        , 2) AS negative_vouchers,
        ROUND(
              MAX(CASE WHEN sc.name = 'Salario Base' THEN pd.amount * {$currency} ELSE 0 END) / 2
            + MAX(CASE WHEN sc.name = 'Bono de Alimentación' THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Bono de Transporte' THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Bono de Productos Asignados' THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Bono Vacacional' THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Bono de Rendimiento' THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Bono de Facturas' THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Bono de Ventas' THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Bono de Crecimiento de Ventas' THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Bono de Ayuda familiar' THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Vacaciones' THEN pd.amount * {$currency} ELSE 0 END)
            + MAX(CASE WHEN sc.name = 'Utilidades' THEN pd.amount * {$currency} ELSE 0 END)
            - MAX(CASE WHEN sc.name = 'Seguro Social' THEN pd.amount * {$currency} ELSE 0 END)
            - MAX(CASE WHEN sc.name = 'Liquidación' THEN pd.amount * {$currency} ELSE 0 END)
            - MAX(CASE WHEN sc.name = 'Prestamos' THEN pd.amount * {$currency} ELSE 0 END)
            - MAX(CASE WHEN sc.name = 'Prestacional de Empleo' THEN pd.amount * {$currency} ELSE 0 END)
            - MAX(CASE WHEN sc.name = 'Prestación Vivienda y Hacienda' THEN pd.amount * {$currency} ELSE 0 END)
            - MAX(CASE WHEN sc.name = 'Dias no Trabajados' THEN pd.amount * {$currency} ELSE 0 END)
        , 2) AS total"
      )
      ->leftJoin('payslip_details AS pd', 'pd.payslip_id', '=', 'payslips.id')
      ->leftJoin('users_salary_details AS usd', 'usd.id', '=', 'pd.users_salary_details_id')
      ->leftJoin('salary_concepts AS sc', 'sc.id', '=', 'usd.salary_concept_id')
      ->leftJoin('users', 'users.id', '=', 'usd.user_id')
      ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
      ->leftJoin('employees', 'employees.user_id', '=', 'users.id')
      ->where('payslips.id', $payslip->id)
      ->where('employees.is_active', 1)
      ->whereIn('sc.name', ['Bono de Alimentación', 'Salario Base'])
      ->groupBy(
        'employees.id',
        'employees.name',
        'employees.last_name',
        'employees.identification',
        'roles.name'
      )
      ->orderBy('id');

    return $query;
  }

  public function getData(Payslip $payslip): array
  {
    $end_period = Carbon::createFromFormat('Y-m-d', $payslip->payslip_date);
    $start_period = $end_period->subWeeks(2)->format('d/m/Y');
    $end_period = $end_period->addWeeks(2)->format('d/m/Y');
    $period = "{$start_period} hasta el {$end_period}";

    return [
      'items' => $this->exportableData($payslip)->get()->toArray(),
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
