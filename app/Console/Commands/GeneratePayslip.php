<?php

namespace App\Console\Commands;

use App\Services\PayslipServices;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GeneratePayslip extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-payslip';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera la nómina de empleados usando la fecha actual como referencia';

    /**
     * Execute the console command.
     */
    public function handle(PayslipServices $payslipServices)
    {
        $targetDate = Carbon::today();
        $payslipServices->generate($targetDate);

        $date = $targetDate->toDateString();
        $this->info("Payslip ($date) generated successfully.");
    }
}
