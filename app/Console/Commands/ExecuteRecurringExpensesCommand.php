<?php

namespace App\Console\Commands;

use App\Services\ExpensesServices;
use Illuminate\Console\Command;

class ExecuteRecurringExpensesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:execute-recurring-expenses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(ExpensesServices $expenses)
    {
        //
        $expenses->ejecutarGastosRecurrentesDeHoy();
    }
}
