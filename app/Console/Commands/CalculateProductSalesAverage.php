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
                    $now = Carbon::now();

                    // Ventana de análisis: siempre últimos 12 meses para capturar historial
                    // independiente de cuándo se creó el registro en esta tabla.
                    $windowMonths = 12;

                    // Fecha de inicio de la ventana de 12 meses
                    $windowStart = $now->copy()->subMonths($windowMonths);

                    // Total de unidades vendidas en los últimos 12 meses (órdenes completadas)
                    $totalSold = DB::table('order_details')
                        ->join('orders', 'order_details.order_id', '=', 'orders.id')
                        ->where('order_details.product_id', $product->id)
                        ->where('orders.status', 'Completed')
                        ->where('orders.created_at', '>=', $windowStart)
                        ->sum('order_details.quantity');

                    // Si no hay ventas en la ventana, establecer sales_average en 0
                    if ($totalSold === null || $totalSold == 0) {
                        $salesAverage = 0;
                    } else {
                        // Promedio mensual = ventas en ventana / meses de la ventana
                        $salesAverage = round($totalSold / $windowMonths, 2);
                    }

                    // Actualizar el producto
                    $product->update(['sales_average' => $salesAverage]);
                    $updatedProducts++;

                    $this->info(
                        "Producto ID {$product->id} ({$product->name}): " .
                        "Total vendido: {$totalSold}, " .
                        "Meses base: {$windowMonths}, " .
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
