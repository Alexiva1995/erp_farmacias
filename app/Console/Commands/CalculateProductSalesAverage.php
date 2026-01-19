<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CalculateProductSalesAverage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:calculate-product-sales-average {--chunk=100 : Number of products to process at once}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calcula y actualiza el promedio mensual de ventas (sales_average) de todos los productos basándose en las ventas reales desde la creación del producto';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando cálculo del promedio mensual de ventas de productos...');

        $chunkSize = $this->option('chunk');
        $totalProducts = Product::count();
        $processedProducts = 0;
        $updatedProducts = 0;
        $skippedProducts = 0;

        $this->info("Procesando {$totalProducts} productos en lotes de {$chunkSize}...");

        $progressBar = $this->output->createProgressBar($totalProducts);
        $progressBar->start();

        try {
            DB::beginTransaction();

            Product::chunk($chunkSize, function ($products) use (&$processedProducts, &$updatedProducts, &$skippedProducts, $progressBar) {
                foreach ($products as $product) {
                    $processedProducts++;

                    // Calcular meses desde la creación del producto hasta hoy
                    $createdAt = Carbon::parse($product->created_at);
                    $now = Carbon::now();
                    $monthsSinceCreation = $createdAt->diffInMonths($now);

                    // Si el producto fue creado hace menos de 1 mes, usar 1 mes mínimo para evitar división por cero
                    if ($monthsSinceCreation < 1) {
                        $monthsSinceCreation = 1;
                    }

                    // Calcular total de unidades vendidas desde order_details donde la orden esté completada
                    $totalSold = DB::table('order_details')
                        ->join('orders', 'order_details.order_id', '=', 'orders.id')
                        ->where('order_details.product_id', $product->id)
                        ->where('orders.status', 'Completed')
                        ->sum('order_details.quantity');

                    // Si no hay ventas, establecer sales_average en 0
                    if ($totalSold === null || $totalSold == 0) {
                        $salesAverage = 0;
                    } else {
                        // Calcular promedio mensual: total vendido / meses desde creación
                        $salesAverage = round($totalSold / $monthsSinceCreation, 2);
                    }

                    // Actualizar el producto
                    $product->update(['sales_average' => $salesAverage]);
                    $updatedProducts++;

                    $this->info(
                        "Producto ID {$product->id} ({$product->name}): " .
                        "Total vendido: {$totalSold}, " .
                        "Meses: {$monthsSinceCreation}, " .
                        "Promedio mensual: {$salesAverage}",
                        'v'
                    );

                    $progressBar->advance();
                }
            });

            DB::commit();

            $progressBar->finish();
            $this->newLine(2);

            $this->info('Cálculo del promedio mensual de ventas completado exitosamente!');
            $this->table(
                ['Métrica', 'Cantidad'],
                [
                    ['Total de Productos Procesados', $processedProducts],
                    ['Productos Actualizados', $updatedProducts],
                ]
            );

        } catch (\Exception $e) {
            DB::rollBack();
            $progressBar->finish();
            $this->newLine();
            $this->error('Ocurrió un error durante el cálculo: ' . $e->getMessage());
            $this->error('Transacción revertida. No se hicieron cambios.');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
