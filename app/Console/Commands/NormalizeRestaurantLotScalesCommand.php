<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductLot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeRestaurantLotScalesCommand extends Command
{
    /**
     * El nombre y la firma del comando.
     *
     * @var string
     */
    protected $signature = 'restaurant:normalize-lot-scales
                            {--product= : ID específico del producto a normalizar}
                            {--dry-run : Simular sin persistir cambios en la base de datos}';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Normaliza lotes de insumos de restaurante ingresados en unidades base (g/ml) para convertirlos a empaques según su presentación.';

    /**
     * Ejecuta el comando.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $productId = $this->option('product') ? (int) $this->option('product') : null;

        $this->info('===========================================================');
        $this->info(' NORMALIZADOR DE ESCALAS DE LOTES DE RESTAURANTE (EMPAQUES)');
        $this->info('===========================================================');

        if ($isDryRun) {
            $this->warn('MODO DRY-RUN: No se aplicarán cambios a la base de datos.');
        }

        $query = Product::query()
            ->whereIn('unit_of_measure', ['g', 'G', 'ml', 'ML', 'gr', 'GR'])
            ->where('presentation', '>', 1);

        if ($productId) {
            $query->where('id', $productId);
        }

        $products = $query->with('lots')->get();

        if ($products->isEmpty()) {
            $this->info('No se encontraron insumos con presentación y unidad de medida aplicables.');
            return 0;
        }

        $totalFixedLots = 0;

        foreach ($products as $product) {
            $presentation = (float) $product->presentation;
            if ($presentation <= 1) {
                continue;
            }

            foreach ($product->lots as $lot) {
                $qty = (float) $lot->quantity;
                // Si la cantidad del lote es mayor o igual a la presentación, fue ingresado en gramos/ml directos
                if ($qty >= $presentation) {
                    $newQty = round($qty / $presentation, 4);
                    $this->line("Producto [{$product->id}] {$product->name} - Lote {$lot->lot_number}: {$qty} -> {$newQty} empaques (dividiendo por {$presentation})");

                    if (!$isDryRun) {
                        DB::transaction(function () use ($lot, $newQty, $presentation) {
                            $lot->quantity = $newQty;
                            $lot->saveQuietly();

                            // Normalizar también movimientos iniciales asociados si existen
                            InventoryMovement::where('product_lot_id', $lot->id)
                                ->where('quantity', '>', $lot->quantity)
                                ->get()
                                ->each(function ($mov) use ($presentation) {
                                    $mov->quantity = round((float) $mov->quantity / $presentation, 4);
                                    $mov->stock_before = round((float) $mov->stock_before / $presentation, 4);
                                    $mov->stock_after = round((float) $mov->stock_after / $presentation, 4);
                                    $mov->saveQuietly();
                                });
                        });
                    }

                    $totalFixedLots++;
                }
            }
        }

        $this->info("Proceso completado. Lotes corregidos: {$totalFixedLots}");

        return 0;
    }
}
