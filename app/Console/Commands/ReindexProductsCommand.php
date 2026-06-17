<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReindexProductsCommand extends Command
{
    /**
     * El nombre y la firma del comando en la consola.
     *
     * @var string
     */
    protected $signature = 'products:reindex-to-1000 {--start= : ID de inicio para la reindexación (opcional)}';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Reindexa en cascada todos los IDs de productos menores a 1000 para que empiecen desde 1000.';

    /**
     * Las tablas y columnas que tienen claves foráneas o referencias a products.id.
     *
     * @var array
     */
    protected array $referencingTables = [
        'auto_order_details' => 'product_id',
        'dish_ingredients' => 'product_id',
        'employee_product' => 'product_id',
        'expirations' => 'product_id',
        'expired_logs' => 'product_id',
        'fiscal_history_details' => 'product_id',
        'individual_offers' => 'product_id',
        'inventory_movements' => 'product_id',
        'invoice_details' => 'product_id',
        'invoice_returns' => 'product_id',
        'invoices_counts' => 'product_id',
        'order_details' => 'product_id',
        'price_adjustment_logs' => 'product_id',
        'product_counts' => 'product_id',
        'product_failures' => 'product_id',
        'product_lots' => 'product_id',
        'product_pack_items' => 'product_id',
        'product_profitability' => 'product_id',
        'product_suppliers' => 'product_id',
        'psychotropic_controls' => 'product_id',
        'quotation_products' => 'product_id',
        'returns' => 'product_id',
        'sales_counts' => 'product_id',
    ];

    /**
     * Ejecuta el comando.
     */
    public function handle(): int
    {
        // Obtener todos los productos con ID < 1000 ordenados por ID
        $products = DB::table('products')
            ->where('id', '<', 1000)
            ->orderBy('id', 'asc')
            ->get();

        if ($products->isEmpty()) {
            $this->info('No se encontraron productos con ID menor a 1000.');
            return 0;
        }

        // Determinar el ID de inicio para los nuevos IDs (por defecto 1000)
        $startId = $this->option('start');
        $currentNewId = $startId !== null ? (int) $startId : 1000;

        $this->info("Iniciando reindexación de {$products->count()} productos. Nuevo rango empezará en: {$currentNewId}");

        // Confirmar la operación
        if (!$this->confirm('¿Estás seguro de que deseas reindexar estos productos y todas sus referencias en cascada?')) {
            $this->warn('Operación cancelada.');
            return 1;
        }

        try {
            DB::transaction(function () use ($products, $currentNewId) {
                // Desactivar temporalmente la verificación de claves foráneas
                DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

                foreach ($products as $product) {
                    $oldId = $product->id;
                    $newId = $currentNewId;

                    $this->line("Reindexando: ID {$oldId} -> ID {$newId} ({$product->name})");

                    // 1. Actualizar las tablas dependientes
                    foreach ($this->referencingTables as $table => $column) {
                        DB::table($table)
                            ->where($column, $oldId)
                            ->update([$column => $newId]);
                    }

                    // 2. Actualizar el producto en la tabla principal
                    DB::table('products')
                        ->where('id', $oldId)
                        ->update(['id' => $newId]);

                    $currentNewId++;
                }

                // Volver a activar la verificación de claves foráneas
                DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
            });

            $this->info('¡Reindexación en cascada completada exitosamente!');
            return 0;

        } catch (\Exception $e) {
            // Asegurar que las llaves foráneas se vuelvan a activar si hay un error
            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
            $this->error('Ocurrió un error durante la reindexación: ' . $e->getMessage());
            return 1;
        }
    }
}
