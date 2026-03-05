<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EvaluateSuppliersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'suppliers:evaluate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evalúa masivamente el desempeño de todos los proveedores y actualiza sus puntajes.';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\Suppliers\SupplierEvaluationService $evaluationService)
    {
        $this->info('Iniciando evaluación masiva de proveedores...');
        
        $evaluationService->evaluateAll();

        $this->info('Evaluación completada. Se han actualizado los puntajes en supplier_scores.');
    }
}
