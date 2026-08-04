<?php

namespace App\Console\Commands;

use App\Models\CleaningActivityExecution;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateCleaningActivityExecutions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleaning:generate-executions 
                            {--days=0 : Número de días hacia adelante para generar ejecuciones}
                            {--force : Forzar regeneración incluso si ya existen}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera las ejecuciones de actividades de limpieza según la frecuencia asignada a cada empleado';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧹 Iniciando generación de ejecuciones de actividades de limpieza...');

        $daysAhead = (int) $this->option('days');
        $force = $this->option('force');

        $startDate = Carbon::today();
        $endDate = Carbon::today()->addDays($daysAhead);

        $this->info("📅 Generando ejecuciones desde {$startDate->format('Y-m-d')} hasta {$endDate->format('Y-m-d')}");

        try {
            DB::beginTransaction();

            $stats = [
                'generated' => 0,
                'skipped' => 0,
                'errors' => 0,
            ];

            // Obtener todos los empleados activos con sus actividades asignadas
            $employees = Employee::where('is_active', true)
                ->with([
                    'cleaningActivities' => function ($query) {
                        $query->withPivot(['status', 'assigned_date']);
                    }
                ])
                ->get();

            if ($employees->isEmpty()) {
                $this->warn('⚠️  No hay empleados activos con actividades asignadas.');
                return Command::SUCCESS;
            }

            $this->info("👥 Procesando {$employees->count()} empleados...");

            $progressBar = $this->output->createProgressBar($employees->count());
            $progressBar->start();

            foreach ($employees as $employee) {
                foreach ($employee->cleaningActivities as $activity) {
                    // Solo procesar si la asignación está activa
                    if ($activity->pivot->status === 'Cancelada') {
                        continue;
                    }

                    $result = $this->generateExecutionsForActivity(
                        $employee,
                        $activity,
                        $startDate,
                        $endDate,
                        $force
                    );

                    $stats['generated'] += $result['generated'];
                    $stats['skipped'] += $result['skipped'];
                    $stats['errors'] += $result['errors'];
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            DB::commit();

            // Mostrar estadísticas
            $this->info('✅ Generación completada exitosamente!');
            $this->table(
                ['Métrica', 'Cantidad'],
                [
                    ['Ejecuciones generadas', $stats['generated']],
                    ['Ejecuciones omitidas (ya existen)', $stats['skipped']],
                    ['Errores', $stats['errors']],
                ]
            );

            // Marcar como vencidas las actividades pendientes del pasado
            $this->markOverdueExecutions();

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error al generar ejecuciones: ' . $e->getMessage());
            Log::error('Error en cleaning:generate-executions', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Genera ejecuciones para una actividad específica de un empleado
     */
    private function generateExecutionsForActivity(
        Employee $employee,
        $activity,
        Carbon $startDate,
        Carbon $endDate,
        bool $force
    ): array {
        $stats = [
            'generated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $frequency = $activity->frequency;
        $assignedDate = Carbon::parse($activity->pivot->assigned_date);

        // Obtener las fechas según la frecuencia
        $scheduledDates = $this->calculateScheduledDates(
            $frequency,
            $assignedDate,
            $startDate,
            $endDate
        );

        foreach ($scheduledDates as $scheduledDate) {
            try {
                // Calcular fecha límite según frecuencia
                $dueDate = $this->calculateDueDate($scheduledDate->copy(), $frequency);

                $exists = CleaningActivityExecution::where('employee_id', $employee->id)
                    ->where('cleaning_activity_id', $activity->id)
                    ->where('scheduled_date', $scheduledDate->format('Y-m-d'))
                    ->exists();

                if ($exists && !$force) {
                    $stats['skipped']++;
                    continue;
                }

                // Si existe y es force, actualizar; si no, crear
                if ($exists && $force) {
                    CleaningActivityExecution::where('employee_id', $employee->id)
                        ->where('cleaning_activity_id', $activity->id)
                        ->where('scheduled_date', $scheduledDate->format('Y-m-d'))
                        ->update([
                            'status' => 'Pendiente',
                            'due_date' => $dueDate->format('Y-m-d'),
                            'updated_at' => now(),
                        ]);
                } else {
                    CleaningActivityExecution::create([
                        'employee_id' => $employee->id,
                        'cleaning_activity_id' => $activity->id,
                        'scheduled_date' => $scheduledDate->format('Y-m-d'),
                        'due_date' => $dueDate->format('Y-m-d'),
                        'status' => 'Pendiente',
                    ]);
                }

                $stats['generated']++;

            } catch (\Exception $e) {
                $stats['errors']++;
                Log::error('Error al crear ejecución', [
                    'employee_id' => $employee->id,
                    'activity_id' => $activity->id,
                    'scheduled_date' => $scheduledDate->format('Y-m-d'),
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $stats;
    }

    /**
     * Calcula las fechas programadas según la frecuencia
     */
    private function calculateScheduledDates(
        string $frequency,
        Carbon $assignedDate,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $dates = [];
        $currentDate = $startDate->copy();

        // Asegurar que empezamos desde la fecha de asignación si es posterior
        if ($assignedDate->isAfter($startDate)) {
            $currentDate = $assignedDate->copy();
        }

        while ($currentDate->lte($endDate)) {
            $dates[] = $currentDate->copy();

            // Calcular la siguiente fecha según la frecuencia
            switch ($frequency) {
                case 'Diaria':
                    $currentDate->addDay();
                    break;

                case 'Semanal':
                    $currentDate->addWeek();
                    break;

                case 'Bimestral':
                    $currentDate->addMonths(2);
                    break;

                case 'Mensual':
                    $currentDate->addMonth();
                    break;

                case 'Trimestral':
                    $currentDate->addMonths(3);
                    break;

                case 'Semestral':
                    $currentDate->addMonths(6);
                    break;

                case 'Anual':
                    $currentDate->addYear();
                    break;

                default:
                    // Si no reconocemos la frecuencia, salir del loop
                    $this->warn("⚠️  Frecuencia desconocida: {$frequency}");
                    break 2;
            }
        }

        return $dates;
    }

    /**
     * Calcula la fecha límite según la frecuencia
     */
    private function calculateDueDate(Carbon $scheduledDate, string $frequency): Carbon
    {
        $dueDate = $scheduledDate->copy();

        switch ($frequency) {
            case 'Diaria':
                // Debe completarse el mismo día (al final del día)
                $dueDate->endOfDay();
                break;

            case 'Semanal':
                // Debe completarse al final de la semana (Domingo)
                $dueDate->endOfWeek();
                break;

            case 'Bimestral':
                // Tiene 2 meses para completarla (al final del segundo mes)
                $dueDate->addMonths(2)->endOfMonth();
                break;

            case 'Mensual':
                // Debe completarse al final del mes
                $dueDate->endOfMonth();
                break;

            case 'Trimestral':
                // Tiene 3 meses para completarla (al final del tercer mes)
                $dueDate->addMonths(3)->endOfMonth();
                break;

            case 'Semestral':
                // Tiene 6 meses para completarla (al final del sexto mes)
                $dueDate->addMonths(6)->endOfMonth();
                break;

            case 'Anual':
                // Tiene 1 año para completarla (al final del año)
                $dueDate->addYear()->endOfMonth();
                break;

            default:
                // Por defecto, 7 días
                $dueDate->addDays(7)->endOfDay();
                break;
        }

        return $dueDate;
    }

    /**
     * Marca como vencidas las ejecuciones pendientes cuya fecha límite ya pasó
     */
    private function markOverdueExecutions(): void
    {
        $this->info('🕐 Renovando ejecuciones vencidas...');

        $overdueExecutions = CleaningActivityExecution::where('status', 'Pendiente')
            ->where('due_date', '<', Carbon::today())
            ->get();

        $updatedCount = 0;

        foreach ($overdueExecutions as $execution) {
            $execution->update([
                'status' => 'Vencida',
                'updated_at' => now(),
            ]);

            $updatedCount++;
        }

        if ($updatedCount > 0) {
            $this->info("🔄 {$updatedCount} ejecuciones marcadas como vencidas.");
        } else {
            $this->info('✓ No hay ejecuciones para marcar como vencidas.');
        }
    }
}
