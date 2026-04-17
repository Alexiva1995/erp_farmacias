<?php

namespace App\Console\Commands;

use App\Services\PayslipServices;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GeneratePayslip extends Command
{
    protected $signature = 'app:generate-payslip {--date= : Fecha específica para generar la nómina (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera la nómina de empleados usando la fecha proporcionada o la actual';

    /**
     * Execute the console command.
     */
    public function handle(PayslipServices $payslipServices)
    {
        $dateInput = $this->option('date');
        $targetDate = $dateInput ? Carbon::parse($dateInput) : Carbon::today();

        $payslipServices->generate($targetDate);

        $date = $targetDate->toDateString();
        $this->info("Payslip ($date) generated successfully.");
    }
}
