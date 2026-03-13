<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\ExchangeRate;
use App\Models\Payslip;
use App\Models\PayslipDetails;
use App\Models\SalaryConcept;
use App\Models\UsersSalaryDetails;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GeneratePayroll2025 extends Command
{
    protected $signature = 'app:generate-2025-payroll';
    protected $description = 'Genera la nómina legal para todo el año 2025 con tasas específicas';

    private $rates = [
        1 => [15 => 37.50, 30 => 38.42],
        2 => [15 => 39.15, 30 => 39.90],
        3 => [15 => 41.20, 30 => 42.35],
        4 => [15 => 43.80, 30 => 45.10],
        5 => [15 => 47.25, 30 => 49.60],
        6 => [15 => 52.10, 30 => 55.45],
        7 => [15 => 58.90, 30 => 64.20],
        8 => [15 => 72.15, 30 => 81.40],
        9 => [15 => 95.60, 30 => 110.25],
        10 => [15 => 135.40, 30 => 162.10],
        11 => [15 => 195.80, 30 => 235.50],
        12 => [15 => 285.30, 30 => 345.15],
    ];

    private $employeesData = [
        ['name' => 'Yeniret de los angeles Itanare canache', 'id' => '30.335.463', 'salary' => 97.50],
        ['name' => 'Manuel Alfonso Pirela aranque', 'id' => '9.399.935', 'salary' => 160.00],
        ['name' => 'Jackelin Adriana Valero vivero', 'id' => '34.917.767', 'salary' => 72.5],
        ['name' => 'Mayela Coromoto Morales Mora', 'id' => '9.351.893', 'salary' => 85.00],
        ['name' => 'Oriana anelix Barboza colmenares', 'id' => '32.394.926', 'salary' => 72.50],
        ['name' => 'Paola de los Ángeles Barreto peña', 'id' => '28.017.946', 'salary' => 85.00],
        ['name' => 'María Antonia Martinez Espitia', 'id' => '32.130.078', 'salary' => 110.00],
    ];

    public function handle()
    {
        $this->info('Iniciando generación de nómina 2025...');

        DB::transaction(function () {
            foreach ($this->rates as $month => $days) {
                foreach ($days as $day => $rate) {
                    $date = Carbon::create(2025, $month, $day);
                    
                    // 1. Asegurar tasa de cambio
                    $exchangeRate = ExchangeRate::firstOrCreate(
                        ['currency_code' => 'BS', 'created_at' => $date->startOfDay()->toDateTimeString()],
                        ['rate' => $rate, 'updated_at' => $date]
                    );

                    // 2. Crear cabecera de nómina
                    $monthName = $date->locale('es')->monthName;
                    $type = $day === 15 ? 'Nomina quincena' : 'Nomina fin de mes';
                    $payslipName = "$type ($monthName) 2025";

                    $payslip = Payslip::create([
                        'payslip_date' => $date->format('Y-m-d'),
                        'name' => $payslipName,
                        'total' => 0,
                        'exchange_rate' => $rate,
                        'status' => 0
                    ]);

                    $totalPayslipUsd = 0;

                    foreach ($this->employeesData as $emp) {
                        $employee = Employee::where('identification', 'like', '%' . preg_replace('/[^0-9]/', '', $emp['id']) . '%')->first();

                        if (!$employee) {
                            $this->warn("Empleado no encontrado: {$emp['name']} ({$emp['id']})");
                            continue;
                        }

                        $baseSalaryUsd = $emp['salary'];
                        $isSecondNomina = ($day > 15);
                        
                        // Conceptos
                        $concepts = [
                            ['name' => 'Salario Básico Mensual', 'amount' => round($baseSalaryUsd / 2, 2)],
                        ];

                        if ($isSecondNomina) {
                            $concepts[] = ['name' => 'Bono de Alimentación', 'amount' => 40.00];
                            
                            // Deducciones básicas (siguiendo lógica de PayslipRepository)
                            $concepts[] = ['name' => 'IVSS (4%)', 'amount' => -round($baseSalaryUsd * 0.04, 2)];
                            $concepts[] = ['name' => 'RPE - Paro Forzoso (0.5%)', 'amount' => -round($baseSalaryUsd * 0.005, 2)];
                            $concepts[] = ['name' => 'FAOV (1%)', 'amount' => -round($baseSalaryUsd * 0.01, 2)];
                        }

                        foreach ($concepts as $c) {
                            $concept = SalaryConcept::where('name', $c['name'])->first();
                            if (!$concept) continue;

                            $usd = UsersSalaryDetails::firstOrCreate(
                                ['user_id' => $employee->user_id, 'salary_concept_id' => $concept->id],
                                ['amount' => 0]
                            );

                            PayslipDetails::create([
                                'payslip_id' => $payslip->id,
                                'users_salary_details_id' => $usd->id,
                                'amount' => $c['amount'],
                            ]);

                            if ($c['amount'] > 0) {
                                $totalPayslipUsd += $c['amount'];
                            }
                        }
                    }

                    $payslip->update(['total' => $totalPayslipUsd]);
                    $this->line("Generada: $payslipName con tasa $rate");
                }
            }
        });

        $this->info('Nómina 2025 generada exitosamente.');
    }
}
