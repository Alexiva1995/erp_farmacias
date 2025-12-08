<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Models\SalaryConcept;
use App\Models\UsersSalaryDetails;
use App\Models\Payslip;
use App\Models\PayslipDetails;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateAutomaticSocialBenefitsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $type;

    public function __construct($type = 'all')
    {
        $this->type = $type;
    }

    public function handle()
    {
        Log::info('Automatic Social Benefits Job', [
            'message' => 'Iniciando generación automática de prestaciones sociales',
            'type' => $this->type
        ]);

        switch ($this->type) {
            case 'vacations':
                $this->generateVacationBenefits();
                break;
            case 'utilities':
                $this->generateUtilitiesBenefits();
                break;
            case 'all':
            default:
                $this->generateVacationBenefits();
                $this->generateUtilitiesBenefits();
                break;
        }

        Log::info('Automatic Social Benefits Job', [
            'message' => 'Generación automática completada',
            'type' => $this->type
        ]);
    }

    private function generateVacationBenefits()
    {
        // Buscar empleados que cumplieron 1 año en la última quincena
        $employees = Employee::where('is_active', true)
            ->whereRaw('DATEDIFF(NOW(), created_at) >= 365')
            ->whereRaw('DATEDIFF(NOW(), created_at) <= 380') // Últimos 15 días
            ->get();

        Log::info('Automatic Social Benefits Job', [
            'message' => 'Empleados encontrados para vacaciones',
            'count' => $employees->count()
        ]);

        foreach ($employees as $employee) {
            try {
                $this->generateEmployeeVacationBenefits($employee);
                Log::info('Automatic Social Benefits Job', [
                    'message' => 'Vacaciones generadas exitosamente',
                    'employee_id' => $employee->id,
                    'employee_name' => "{$employee->name} {$employee->last_name}"
                ]);
            } catch (\Exception $e) {
                Log::error('Automatic Social Benefits Job', [
                    'message' => 'Error generando vacaciones',
                    'employee_id' => $employee->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function generateUtilitiesBenefits()
    {
        // Solo generar el 16 de diciembre
        if (Carbon::now()->format('m-d') !== '12-16') {
            Log::info('Automatic Social Benefits Job', [
                'message' => 'No es 16 de diciembre, saltando generación de utilidades'
            ]);
            return;
        }

        // Buscar todos los empleados activos con más de 1 año
        $employees = Employee::where('is_active', true)
            ->whereRaw('DATEDIFF(NOW(), created_at) >= 365')
            ->get();

        Log::info('Automatic Social Benefits Job', [
            'message' => 'Empleados encontrados para utilidades',
            'count' => $employees->count()
        ]);

        foreach ($employees as $employee) {
            try {
                $this->generateEmployeeUtilitiesBenefits($employee);
                Log::info('Automatic Social Benefits Job', [
                    'message' => 'Utilidades generadas exitosamente',
                    'employee_id' => $employee->id,
                    'employee_name' => "{$employee->name} {$employee->last_name}"
                ]);
            } catch (\Exception $e) {
                Log::error('Automatic Social Benefits Job', [
                    'message' => 'Error generando utilidades',
                    'employee_id' => $employee->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function generateEmployeeVacationBenefits(Employee $employee)
    {
        // Verificar si ya se generaron las vacaciones este año
        $currentYear = Carbon::now()->year;

        $existingVacation = UsersSalaryDetails::where('user_id', $employee->user_id)
            ->whereHas('concept', function ($query) {
                $query->where('name', 'Vacaciones');
            })
            ->whereYear('created_at', $currentYear)
            ->first();

        if ($existingVacation) {
            Log::info('Automatic Social Benefits Job', [
                'message' => 'Vacaciones ya generadas este año',
                'employee_id' => $employee->id
            ]);
            return;
        }

        // Calcular días de vacaciones según antigüedad
        $activeYears = Carbon::parse($employee->created_at)->diffInYears(Carbon::now());
        $vacationDays = 15 * $activeYears + 1 * ($activeYears === 0 ? 0 : $activeYears - 1);

        // Obtener salario promedio del empleado
        $averageSalary = $this->getEmployeeAverageSalary($employee);
        $dailyWage = $averageSalary / 30;

        // Calcular salario integral
        $integralSalary = $dailyWage + (30 * $dailyWage / 360) + (15 * $dailyWage / 360);

        // Calcular monto de vacaciones
        $vacationAmount = round($vacationDays * $integralSalary, 2);

        // Obtener concepto de vacaciones
        $vacationConcept = SalaryConcept::where('name', 'Vacaciones')->first();
        if (!$vacationConcept) {
            throw new \Exception('Concepto de Vacaciones no encontrado');
        }

        // Crear registro de salario
        $salaryDetail = UsersSalaryDetails::create([
            'user_id' => $employee->user_id,
            'salary_concept_id' => $vacationConcept->id,
            'amount' => $vacationAmount,
        ]);

        // Generar payslip detail automáticamente
        $this->generatePayslipDetail($salaryDetail, $vacationAmount, 'Vacaciones');

        // Generar bono vacacional también
        $this->generateEmployeeVacationBonus($employee, $vacationDays, $integralSalary);
    }

    private function generateEmployeeVacationBonus(Employee $employee, int $vacationDays, float $integralSalary)
    {
        // Verificar si ya se generó el bono vacacional este año
        $currentYear = Carbon::now()->year;

        $existingBonus = UsersSalaryDetails::where('user_id', $employee->user_id)
            ->whereHas('concept', function ($query) {
                $query->where('name', 'Bono Vacacional');
            })
            ->whereYear('created_at', $currentYear)
            ->first();

        if ($existingBonus) {
            Log::info('Automatic Social Benefits Job', [
                'message' => 'Bono vacacional ya generado este año',
                'employee_id' => $employee->id
            ]);
            return;
        }

        // Calcular monto del bono vacacional
        $bonusAmount = round($vacationDays * $integralSalary, 2);

        // Obtener concepto de bono vacacional
        $bonusConcept = SalaryConcept::where('name', 'Bono Vacacional')->first();
        if (!$bonusConcept) {
            throw new \Exception('Concepto de Bono Vacacional no encontrado');
        }

        // Crear registro de salario
        $salaryDetail = UsersSalaryDetails::create([
            'user_id' => $employee->user_id,
            'salary_concept_id' => $bonusConcept->id,
            'amount' => $bonusAmount,
        ]);

        // Generar payslip detail automáticamente
        $this->generatePayslipDetail($salaryDetail, $bonusAmount, 'Bono Vacacional');
    }

    private function generateEmployeeUtilitiesBenefits(Employee $employee)
    {
        // Verificar si ya se generaron las utilidades este año
        $currentYear = Carbon::now()->year;

        $existingUtilities = UsersSalaryDetails::where('user_id', $employee->user_id)
            ->whereHas('concept', function ($query) {
                $query->where('name', 'Utilidades');
            })
            ->whereYear('created_at', $currentYear)
            ->first();

        if ($existingUtilities) {
            Log::info('Automatic Social Benefits Job', [
                'message' => 'Utilidades ya generadas este año',
                'employee_id' => $employee->id
            ]);
            return;
        }

        // Calcular días de utilidades según antigüedad
        $activeYears = Carbon::parse($employee->created_at)->diffInYears(Carbon::now());
        $utilitiesDays = 30 * $activeYears;

        // Obtener salario promedio del empleado
        $averageSalary = $this->getEmployeeAverageSalary($employee);
        $dailyWage = $averageSalary / 30;

        // Calcular salario integral
        $integralSalary = $dailyWage + (30 * $dailyWage / 360) + (15 * $dailyWage / 360);

        // Calcular monto de utilidades
        $utilitiesAmount = round($utilitiesDays * $integralSalary, 2);

        // Obtener concepto de utilidades
        $utilitiesConcept = SalaryConcept::where('name', 'Utilidades')->first();
        if (!$utilitiesConcept) {
            throw new \Exception('Concepto de Utilidades no encontrado');
        }

        // Crear registro de salario
        $salaryDetail = UsersSalaryDetails::create([
            'user_id' => $employee->user_id,
            'salary_concept_id' => $utilitiesConcept->id,
            'amount' => $utilitiesAmount,
        ]);

        // Generar payslip detail automáticamente
        $this->generatePayslipDetail($salaryDetail, $utilitiesAmount, 'Utilidades');
    }

    private function getEmployeeAverageSalary(Employee $employee): float
    {
        // Obtener los últimos 6 salarios en Bolívares
        $salaries = DB::table('employees')
            ->select([
                'pd.amount as amount_bs',
                'ps.payslip_date',
                'er.rate as exchange_rate',
                DB::raw("pd.amount * er.rate AS amount_bs_calculated")
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
            ->limit(6)
            ->get();

        if ($salaries->isEmpty()) {
            return 0;
        }

        $totalAmount = $salaries->sum('amount_bs_calculated');
        $salariesCount = $salaries->count();
        $averageQuincenal = $totalAmount / $salariesCount;
        $averageMonthly = $averageQuincenal * 2; // Convertir de quincenal a mensual

        return round($averageMonthly, 2);
    }

    private function generatePayslipDetail($salaryDetail, $amount, $conceptName)
    {
        try {
            // Buscar o crear un payslip para la fecha actual
            $payslip = Payslip::firstOrCreate(
                [
                    'payslip_date' => Carbon::now()->format('Y-m-d'),
                    'name' => "Prestación Automática - {$conceptName} - " . Carbon::now()->format('Y-m-d'),
                ],
                [
                    'payslip_date' => Carbon::now()->format('Y-m-d'),
                    'name' => "Prestación Automática - {$conceptName} - " . Carbon::now()->format('Y-m-d'),
                    'total' => 0,
                    'status' => 1,
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

            Log::info('Automatic Social Benefits Job', [
                'message' => 'Payslip detail generado automáticamente',
                'payslip_id' => $payslip->id,
                'salary_detail_id' => $salaryDetail->id,
                'amount' => $amount,
                'concept' => $conceptName
            ]);
        } catch (\Exception $e) {
            Log::error('Automatic Social Benefits Job', [
                'message' => 'Error generando payslip detail automáticamente',
                'error' => $e->getMessage(),
                'salary_detail_id' => $salaryDetail->id,
                'concept' => $conceptName
            ]);
            throw $e;
        }
    }
}
