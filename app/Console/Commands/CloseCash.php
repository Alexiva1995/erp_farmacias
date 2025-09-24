<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CashClosing;
use Illuminate\Support\Carbon;


class CloseCash extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:close-cash';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cierre diario de caja.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $cashes = CashClosing::where('estatus',CashClosing::OPEN)->whereDate('created_at', Carbon::today())->get();

            $this->info('Daily cash closure completed successfully!');
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to close the daily cash closure.');
            $this->error($e->getMessage());
            return 1;
        }
    }
}
