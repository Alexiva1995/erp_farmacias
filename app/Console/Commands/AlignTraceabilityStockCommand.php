<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;

class AlignTraceabilityStockCommand extends Command
{
    /**
     * El nombre y la firma del comando.
     *
     * @var string
     */
    protected $signature = 'inventory:align-traceability-stock
                            {--product= : ID específico de producto a alinear}
                            {--dry-run : Simular la alineación sin persistir cambios}';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Genera movimientos de ajuste/pérdida automáticos para que el último movimiento de trazabilidad coincida exactamente con la sumatoria real de los lotes.';

    /**
     * Ejecuta el comando.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $productId = $this->option('product') ? (int) $this->option('product') : null;

        $this->info('====================================================');
        $this->info('  ALINEADOR DE TRAZABILIDAD CON STOCK REAL DE LOTES ');
        $this->info('====================================================');

        if ($isDryRun) {
            $this->warn('MODO DRY-RUN: No se aplicarán cambios a la base de datos.');
        }

        $adminUser = User::where('role_id', 1)->first() ?? User::first();
        $adminUserId = $adminUser ? $adminUser->id : 1;

        $query = Product::query()
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->with(['lots' => function ($q) {
                $q->where('quantity', '>', 0);
            }]);

        if ($productId) {
            $query->where('id', $productId);
        }

        $totalProducts = $query->count();
        if ($totalProducts === 0) {
            $this->error('No se encontraron productos para procesar.');
            return Command::FAILURE;
        }

        $this->info("Analizando {$totalProducts} producto(s)...");

        $adjustedCount = 0;
        $alignedCount = 0;
        $now = now();

        $progressBar = $this->output->createProgressBar($totalProducts);
        $progressBar->start();

        $query->chunk(100, function ($products) use (
            $isDryRun,
            $adminUserId,
            $now,
            &$adjustedCount,
            &$alignedCount,
            $progressBar
        ) {
            foreach ($products as $product) {
                $realStock = (float) $product->lots->sum('quantity');

                // Sincronizar columna stock en tabla products
                if (!$isDryRun && (float) $product->stock !== $realStock) {
                    $product->updateQuietly(['stock' => $realStock]);
                }

                $lastMovement = InventoryMovement::where('product_id', $product->id)
                    ->orderBy('id', 'desc')
                    ->first();

                if ($lastMovement) {
                    $lastStockAfter = (float) $lastMovement->stock_after;
                    $diff = round($realStock - $lastStockAfter, 4);

                    if (abs($diff) > 0.0001) {
                        $adjustedCount++;
                        $movementType = $diff > 0 ? 'adjustment' : 'loss';
                        $targetLot = $product->lots->first();

                        if (!$isDryRun) {
                            InventoryMovement::create([
                                'product_id'       => $product->id,
                                'product_lot_id'   => $targetLot?->id,
                                'movement_type'    => $movementType,
                                'quantity'         => $diff,
                                'invoice_id'       => null,
                                'supplier_id'      => null,
                                'order_id'         => null,
                                'dish_id'          => null,
                                'user_id'          => $adminUserId,
                                'product_count_id' => null,
                                'stock_before'     => $lastStockAfter,
                                'stock_after'      => $realStock,
                                'movement_date'    => $now,
                            ]);
                        }

                        $this->info(
                            " [AJUSTADO] Prod #{$product->id} ({$product->name}): Trazabilidad {$lastStockAfter} -> Lotes {$realStock} (Dif: {$diff})",
                            'v'
                        );
                    } else {
                        $alignedCount++;
                    }
                } else {
                    // Si el producto no tiene ningún movimiento pero tiene stock físico
                    if ($realStock > 0) {
                        $adjustedCount++;
                        $targetLot = $product->lots->first();

                        if (!$isDryRun) {
                            InventoryMovement::create([
                                'product_id'       => $product->id,
                                'product_lot_id'   => $targetLot?->id,
                                'movement_type'    => 'adjustment',
                                'quantity'         => $realStock,
                                'invoice_id'       => null,
                                'supplier_id'      => null,
                                'order_id'         => null,
                                'dish_id'          => null,
                                'user_id'          => $adminUserId,
                                'product_count_id' => null,
                                'stock_before'     => 0,
                                'stock_after'      => $realStock,
                                'movement_date'    => $now,
                            ]);
                        }

                        $this->info(
                            " [INICIALIZADO] Prod #{$product->id} ({$product->name}): Sin movimientos -> Lotes {$realStock}",
                            'v'
                        );
                    } else {
                        $alignedCount++;
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
        $this->info("Productos analizados:           {$totalProducts}");
        $this->info("Productos ya alineados:         {$alignedCount}");
        $this->info("Productos ajustados/corregidos: {$adjustedCount}");

        if ($isDryRun) {
            $this->warn('Ejecución en modo simulación. Ningún registro fue modificado.');
        } else {
            $this->info('Trazabilidad y stock de catálogo sincronizados con éxito con los lotes reales.');
        }

        return Command::SUCCESS;
    }
}
