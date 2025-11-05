<?php

namespace App\Jobs;

use App\Services\PayslipServices;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GeneratePayslipJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     */
    public function handle(PayslipServices $payslipServices): void
    {
        $targetDate = Carbon::today();
        $payslipServices->generate($targetDate);
    }
}
