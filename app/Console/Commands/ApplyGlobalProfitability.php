<?php

namespace App\Console\Commands;

use App\Services\ProfitabilityServices;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ApplyGlobalProfitability extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:apply-global-profitability';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aplica el porcentaje de rentabilidad global a todos los productos no bloqueados.';

    /**
     * Execute the console command.
     */
    public function handle(ProfitabilityServices $profitabilityServices): int
    {
        $this->info('Iniciando aplicación de rentabilidad global...');

        try {
            $profitabilityServices->applyGlobalProfitabilityToAllProducts();
            
            $this->info('Proceso completado exitosamente.');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error durante la ejecución: ' . $e->getMessage());
            Log::error('Error en comando app:apply-global-profitability: ' . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
}
