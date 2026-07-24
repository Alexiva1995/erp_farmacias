<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Log;

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
        /*$sellers = User::all();
        $cashClosureService = app(\App\Services\CashClosure\CashClosureActionService::class);
        foreach ($sellers as $seller) {
            try {
                $cashClosureService->closeDailyCashClosure($seller);
            } catch (\Exception $e) {
                Log::error("Error al cerrar la caja para el vendedor: {$seller->name}. Mensaje: {$e->getMessage()}");
            }
        }*/

        $cashClosureService = app(\App\Services\CashClosure\CashClosureActionService::class);
        try {
            $cashClosureService->closeDailyCashClosure(); 
        } catch (\Exception $e) {
            Log::error("Error al realizar el cierre de caja diario. Mensaje: {$e->getMessage()}");
        }
    }
}
