<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Lots\LotActionService;
use Illuminate\Console\Command;

class ConsolidateDuplicateLotsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lots:consolidate-duplicates {--product_id= : ID específico del producto a consolidar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consolida lotes duplicados con el mismo número de lote y fecha de vencimiento unificando sus cantidades y referencias.';

    /**
     * Execute the console command.
     */
    public function handle(LotActionService $lotActionService): int
    {
        $productId = $this->option('product_id') ? (int) $this->option('product_id') : null;

        $this->info('Iniciando consolidación de lotes duplicados...');

        $result = $lotActionService->consolidateDuplicateLots($productId);

        $this->info("Consolidación finalizada exitosamente.");
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Productos Afectados', $result['products_affected_count']],
                ['Grupos de Lotes Consolidados', $result['groups_consolidated']],
                ['Lotes Anteriores Marcados en 0', $result['duplicates_zeroed']],
            ]
        );

        return Command::SUCCESS;
    }
}
