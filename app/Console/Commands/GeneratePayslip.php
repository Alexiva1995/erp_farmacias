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
    protected $signature = 'app:generate-payslip {--pay-food-voucher : Pagar bono de alimentacion}';

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
        $payFoodVoucher = $this->option('pay-food-voucher');
        $payslipServices->generate($targetDate, $payFoodVoucher);

        $date = $targetDate->toDateString();
        $this->info("Payslip ($date) generated successfully.");
    }
}
