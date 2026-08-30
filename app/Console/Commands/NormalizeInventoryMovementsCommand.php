<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NormalizeInventoryMovementsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:normalize-inventory-movements
                            {--product= : ID específico del producto a normalizar}
                            {--dry-run : Simular la normalización sin persistir cambios en la base de datos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Normaliza cronológicamente stock_before y stock_after en inventory_movements para alinear el Kardex con la verdad de los lotes.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $productId = $this->option('product') ? (int) $this->option('product') : null;

        $this->info('====================================================');
        $this->info('  NORMALIZADOR DE KARDEX Y MOVIMIENTOS DE STOCK     ');
        $this->info('====================================================');

        if ($isDryRun) {
            $this->warn('MODO DRY-RUN: No se aplicarán cambios a la base de datos.');
        }

        $query = Product::query()->select(['id', 'name', 'stock']);
        if ($productId) {
            $query->where('id', $productId);
        }

        $totalProducts = $query->count();
        if ($totalProducts === 0) {
            $this->error('No se encontraron productos para procesar.');
            return Command::FAILURE;
        }

        $this->info("Procesando {$totalProducts} producto(s)...");

        $totalMovementsChecked = 0;
        $totalMovementsFixed = 0;
        $productsWithFixes = 0;

        $progressBar = $this->output->createProgressBar($totalProducts);
        $progressBar->start();

        $query->chunk(100, function ($products) use (
            $isDryRun,
            &$totalMovementsChecked,
            &$totalMovementsFixed,
            &$productsWithFixes,
            $progressBar
        ) {
            foreach ($products as $product) {
                $movements = InventoryMovement::where('product_id', $product->id)
                    ->orderBy('id', 'asc')
                    ->get();

                if ($movements->isEmpty()) {
                    $progressBar->advance();
                    continue;
                }

                $runningStock = 0.0;
                $productFixedCount = 0;
                $updates = [];

                foreach ($movements as $movement) {
                    $totalMovementsChecked++;
                    $qty = (float) $movement->quantity;
                    $expectedBefore = $runningStock;
                    $expectedAfter = round($runningStock + $qty, 4);

                    $currentBefore = (float) $movement->stock_before;
                    $currentAfter = (float) $movement->stock_after;

                    if (abs($currentBefore - $expectedBefore) > 0.0001 || abs($currentAfter - $expectedAfter) > 0.0001) {
                        $productFixedCount++;
                        $updates[] = [
                            'id' => $movement->id,
                            'stock_before' => $expectedBefore,
                            'stock_after' => $expectedAfter,
                        ];
                    }

                    $runningStock = $expectedAfter;
                }

                if ($productFixedCount > 0) {
                    $productsWithFixes++;
                    $totalMovementsFixed += $productFixedCount;

                    if (!$isDryRun) {
                        DB::transaction(function () use ($updates) {
                            foreach ($updates as $update) {
                                InventoryMovement::where('id', $update['id'])->update([
                                    'stock_before' => $update['stock_before'],
                                    'stock_after' => $update['stock_after'],
                                ]);
                            }
                        });
                    }
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        $this->info('====================================================');
        $this->info('                 RESUMEN FINAL                      ');
        $this->info('====================================================');
        $this->line("Productos analizados:           {$totalProducts}");
        $this->line("Productos con movimientos corr: {$productsWithFixes}");
        $this->line("Total movimientos evaluados:    {$totalMovementsChecked}");
        $this->line("Total movimientos corregidos:   {$totalMovementsFixed}");

        if ($isDryRun) {
            $this->warn('Ejecución en modo simulación finalizada. Ningún registro fue modificado.');
        } else {
            $this->info('Normalización completada exitosamente.');
        }

        return Command::SUCCESS;
    }
}
