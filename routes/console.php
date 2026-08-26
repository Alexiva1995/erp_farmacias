<?php

use App\Jobs\ExecuteRecurringExpensesJob;
use App\Jobs\GeneratePayslipJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:update-exchange-rate')->dailyAt('00:10');
Schedule::command('app:close-cash')->dailyAt('23:59');
Schedule::command('app:clear-expired-reservations')->everyMinute();
Schedule::command('telegram:send-daily-reservations')->dailyAt('12:00');
Schedule::command('telegram:send-upcoming-payments')->dailyAt('09:30');
Schedule::job(new GeneratePayslipJob())->monthlyOn(15, '00:00');
Schedule::job(new GeneratePayslipJob())->lastDayOfMonth('00:00');
Schedule::command("app:execute-recurring-expenses")->daily();
Schedule::command('cleaning:generate-executions --days=0')
    ->dailyAt('00:01')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Log::error('Fallo al generar ejecuciones de limpieza');
    });

Schedule::call(function () {
    $overdueExecutions = \App\Models\CleaningActivityExecution::whereIn('status', ['Pendiente', 'Vencida'])
        ->where('due_date', '<', now()->startOfDay())
        ->with('cleaningActivity')
        ->get();

    $renewedCount = 0;
    foreach ($overdueExecutions as $execution) {
        $activity = $execution->cleaningActivity;
        if (!$activity) continue;

        $newScheduled = now()->startOfDay();
        $dueDate = $newScheduled->copy();
        
        switch ($activity->frequency) {
            case 'Diaria': $dueDate->endOfDay(); break;
            case 'Semanal': $dueDate->endOfWeek(); break;
            case 'Bimestral': $dueDate->addMonths(2)->endOfMonth(); break;
            case 'Mensual': $dueDate->endOfMonth(); break;
            case 'Trimestral': $dueDate->addMonths(3)->endOfMonth(); break;
            case 'Semestral': $dueDate->addMonths(6)->endOfMonth(); break;
            case 'Anual': $dueDate->addYear()->endOfMonth(); break;
            default: $dueDate->addDays(7)->endOfDay(); break;
        }

        $execution->update([
            'scheduled_date' => $newScheduled->format('Y-m-d'),
            'due_date' => $dueDate->format('Y-m-d'),
            'status' => 'Pendiente',
            'updated_at' => now()
        ]);
    }
})
    ->hourly()
    ->name('mark-overdue-executions')
    ->withoutOverlapping();
Schedule::command('app:schedule-automatic-social-benefits')->dailyAt('06:00');
Schedule::command('app:inventory-update-daily')->dailyAt('00:01')->onOneServer()->withoutOverlapping();
Schedule::command('app:calculate-monthly-company-discount')->monthlyOn(1, '00:00');
Schedule::command('app:calculate-product-sales-average')->dailyAt('02:00')->onOneServer()->withoutOverlapping();
Schedule::command('app:classify-clients')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('app:close-monthly-performance')->monthlyOn(1, '00:01');
Schedule::command('suppliers:evaluate')->dailyAt('01:00')->withoutOverlapping();
Schedule::command('app:verify-clients-cne')->everyTwoHours()->withoutOverlapping();
Schedule::command('app:apply-global-profitability')
    ->dailyAt('02:00')
    ->onOneServer()
    ->withoutOverlapping();
Schedule::command('dronena:sync-invoices')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Log::error('[DronenaSync] Falló la sincronización automática de facturas a las 04:00 AM');
    });

Schedule::command('drocerca:sync-invoices')
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->onFailure(function () {
        \Log::error('[DrocercaSync] Falló la sincronización automática de facturas de Drocerca a las 04:30 AM');
    });


// Reposición automática de inventario: cada config activa define su propio cron
// Se lee de BD para registrar cada expresión cron de forma independiente
try {
    $configs = \App\Models\AutoReplenishmentConfig::where('is_active', true)->get();
    foreach ($configs as $config) {
        Schedule::command("replenishment:run --config={$config->id}")
            ->cron($config->schedule_expression)
            ->withoutOverlapping()
            ->runInBackground()
            ->onFailure(function () use ($config) {
                \Log::error("[AutoReplenishment] Fallo en config '{$config->name}'.");
            });
    }
} catch (\Exception $e) {
    // Silenciar advertencia sin interrumpir si la tabla no existe aún (ej. migraciones iniciales)
    \Log::warning('[AutoReplenishment] No se pudo cargar configs: ' . $e->getMessage());
}
