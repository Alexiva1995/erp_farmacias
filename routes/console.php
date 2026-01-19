<?php

use App\Jobs\ExecuteRecurringExpensesJob;
use App\Jobs\GeneratePayslipJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:update-exchange-rate')->dailyAt('00:01');
Schedule::command('app:close-cash')->dailyAt('23:59');
Schedule::job(new GeneratePayslipJob())->monthlyOn(15, '00:00');
Schedule::job(new GeneratePayslipJob())->lastDayOfMonth('00:00');
Schedule::command("app:execute-recurring-expenses")->daily();
Schedule::command('cleaning:generate-executions --days=0')
    ->dailyAt('00:01')
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Log::info('Ejecuciones de limpieza generadas exitosamente');
    })
    ->onFailure(function () {
        \Log::error('Fallo al generar ejecuciones de limpieza');
    });

Schedule::call(function () {
    $overdueCount = \App\Models\CleaningActivityExecution::where('status', 'Pendiente')
        ->where('due_date', '<', now()->startOfDay())
        ->update([
            'status' => 'Vencida',
            'updated_at' => now()
        ]);

    \Log::info("Ejecuciones vencidas actualizadas: {$overdueCount}");
})
    ->hourly()
    ->name('mark-overdue-executions')
    ->withoutOverlapping();
Schedule::command('app:schedule-automatic-social-benefits')->dailyAt('06:00');
Schedule::command('app:inventory-update-daily')->dailyAt('00:01')->onOneServer()->withoutOverlapping();
Schedule::command('app:calculate-monthly-company-discount')->monthlyOn(1, '00:00');
Schedule::command('app:calculate-product-sales-average')->dailyAt('02:00')->onOneServer()->withoutOverlapping();
