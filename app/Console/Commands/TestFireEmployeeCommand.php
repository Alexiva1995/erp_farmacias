<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Repository\SocialBenefitRepository;
use Illuminate\Console\Command;

class TestFireEmployeeCommand extends Command
{
    protected $signature = 'app:test-fire-employee {employee_id}';
    protected $description = 'Probar la funcionalidad de despido de empleados';

    public function handle()
    {
        $employeeId = $this->argument('employee_id');

        $this->info("🔥 Probando despido de empleado ID: {$employeeId}");

        try {
            // Buscar empleado
            $employee = Employee::find($employeeId);

            if (!$employee) {
                $this->error("❌ Empleado con ID {$employeeId} no encontrado");
                return;
            }

            $this->info("👤 Empleado encontrado: {$employee->name} {$employee->last_name}");
            $this->info("📊 Estado actual: " . ($employee->is_active ? 'Activo' : 'Inactivo'));

            if (!$employee->is_active) {
                $this->warn("⚠️  El empleado ya está inactivo");
                return;
            }

            // Probar datos de liquidación
            $this->info("📋 Obteniendo datos de liquidación...");
            $repository = new SocialBenefitRepository();
            $settlementData = $repository->getSettlementData($employee);

            $this->info("📊 Datos de liquidación:");
            $this->info("   - Años de antigüedad: {$settlementData['active_years']}");
            $this->info("   - Monto total: {$settlementData['total_settlement_amount']} Bs.");
            $this->info("   - Monto final USD: {$settlementData['final_usd']} $");
            $this->info("   - Fecha de inicio: {$settlementData['starting_date']}");

            // Verificar que no hay montos negativos
            if ($settlementData['final_usd'] < 0) {
                $this->error("❌ PROBLEMA: Monto negativo detectado: {$settlementData['final_usd']}");
                return;
            }

            $this->info("✅ Monto final no es negativo");

            // Simular datos de despido
            $fireData = [
                'percentage' => 100,
                'total' => $settlementData['final_usd'],
                'currency' => 'USD',
                'count' => 'Efectivo',
                'payed' => $settlementData['final_usd']
            ];

            $this->info("💳 Datos de despido simulados:");
            $this->info("   - Porcentaje: {$fireData['percentage']}%");
            $this->info("   - Total: {$fireData['total']} $");
            $this->info("   - Moneda: {$fireData['currency']}");
            $this->info("   - Método de pago: {$fireData['count']}");
            $this->info("   - Monto pagado: {$fireData['payed']} $");

            // Preguntar si proceder con el despido
            if ($this->confirm('¿Desea proceder con el despido? (Esto cambiará el estado del empleado)')) {
                $this->info("🔥 Procesando despido...");

                $result = $repository->fire($employee, $fireData);

                if ($result) {
                    $this->info("✅ Despido procesado exitosamente");

                    // Verificar estado actualizado
                    $employee->refresh();
                    $this->info("📊 Nuevo estado: " . ($employee->is_active ? 'Activo' : 'Inactivo'));

                    // Verificar registros creados
                    $this->info("🔍 Verificando registros creados...");

                    $settlement = $employee->settlement;
                    if ($settlement) {
                        $this->info("✅ Settlement creado con ID: {$settlement->id}");
                    }
                } else {
                    $this->error("❌ Error al procesar el despido");
                }
            } else {
                $this->info("❌ Despido cancelado por el usuario");
            }
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
        }
    }
}
