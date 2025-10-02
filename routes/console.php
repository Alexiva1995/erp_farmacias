<?php

use App\Jobs\GeneratePayslipJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:update-exchange-rate')->dailyAt('00:01');

Schedule::job(new GeneratePayslipJob())->monthlyOn(15, '00:00');
Schedule::job(new GeneratePayslipJob())->lastDayOfMonth('00:00');
