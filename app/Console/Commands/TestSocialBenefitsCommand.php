<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Repository\SocialBenefitRepository;
use Illuminate\Console\Command;

class TestSocialBenefitsCommand extends Command
{
    protected $signature = 'app:test-social-benefits-module';
    protected $description = 'Probar el módulo de Prestaciones Sociales';

    public function handle()
    {
        $this->info('🧪 Probando Módulo de Prestaciones Sociales...');

        try {
            // Probar obtención de empleados
            $this->info('1. Probando obtención de empleados...');
            $employees = Employee::where('is_active', true)->take(3)->get();

            if ($employees->isEmpty()) {
                $this->warn('⚠️  No se encontraron empleados activos');
                return;
            }

            $this->info("✅ Se encontraron {$employees->count()} empleados activos");

            // Probar repositorio
            $this->info('2. Probando SocialBenefitRepository...');
            $repository = new SocialBenefitRepository();

            foreach ($employees as $employee) {
                $this->info("   Probando empleado: {$employee->name} {$employee->last_name}");

                try {
                    // Probar método index
                    $indexData = $repository->index(['search' => '', 'perPage' => 10]);
                    $this->info("   ✅ Método index() funcionando");

                    // Probar método getSettlementData
                    $settlementData = $repository->getSettlementData($employee);
                    $this->info("   ✅ Método getSettlementData() funcionando");

                    // Mostrar datos importantes
                    $this->info("   📊 Datos de liquidación:");
                    $this->info("      - Años de antigüedad: {$settlementData['active_years']}");
                    $this->info("      - Monto total: {$settlementData['total_settlement_amount']} Bs.");
                    $this->info("      - Monto final USD: {$settlementData['final_usd']} $");
                    $this->info("      - Fecha de inicio: {$settlementData['starting_date']}");

                    // Verificar que no hay montos negativos
                    if ($settlementData['final_usd'] < 0) {
                        $this->error("   ❌ PROBLEMA: Monto negativo detectado: {$settlementData['final_usd']}");
                    } else {
                        $this->info("   ✅ Monto final no es negativo");
                    }
                } catch (\Exception $e) {
                    $this->error("   ❌ Error con empleado {$employee->name}: " . $e->getMessage());
                }

                $this->newLine();
            }

            $this->info('🎉 Pruebas completadas exitosamente');
        } catch (\Exception $e) {
            $this->error('❌ Error general: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}
