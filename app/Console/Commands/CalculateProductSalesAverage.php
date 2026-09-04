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
    protected $description = 'Calcula y actualiza el promedio mensual de ventas (sales_average), timestamps de actualización y factores estacionales por grupo';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando cálculo del promedio mensual de ventas de productos...');

        $now = Carbon::now();

        try {
            DB::beginTransaction();

            // 1. Actualización instantánea en bloque para productos con ventas externas acumuladas del Excel
            $isSqlite = DB::connection()->getDriverName() === 'sqlite';
            if ($isSqlite) {
                $externalUpdated = DB::table('products')
                    ->where('external_accumulated_sales', '>', 0)
                    ->update([
                        'sales_average' => DB::raw('ROUND(external_accumulated_sales / MAX(1, CAST(strftime("%m", COALESCE(external_sales_date, DATE("now"))) AS INTEGER)), 2)'),
                        'sales_average_updated_at' => $now,
                    ]);
            } else {
                $externalUpdated = DB::table('products')
                    ->where('external_accumulated_sales', '>', 0)
                    ->update([
                        'sales_average' => DB::raw('ROUND(external_accumulated_sales / GREATEST(1, MONTH(COALESCE(external_sales_date, CURDATE()))), 2)'),
                        'sales_average_updated_at' => $now,
                    ]);
            }

            $this->info("Productos con ventas acumuladas de catálogo externo actualizados: {$externalUpdated}");

            // 2. Procesar productos con ventas reales locales en ordenes POS
            $chunkSize = $this->option('chunk');
            $windowMonths = 12;
            $windowStart  = $now->copy()->subMonths($windowMonths);

            $processedProducts = 0;
            $updatedProducts = $externalUpdated;

            // Consultar únicamente productos con ventas locales o sin ventas externas para no re-procesar innecesariamente
            $productsToProcess = Product::where(function ($q) {
                $q->whereNull('external_accumulated_sales')
                  ->orWhere('external_accumulated_sales', '<=', 0);
            });

            $totalProducts = $productsToProcess->count();
            $this->info("Procesando {$totalProducts} productos locales en lotes de {$chunkSize}...");

            $progressBar = $this->output->createProgressBar($totalProducts);
            $progressBar->start();

            $productsToProcess->chunk($chunkSize, function ($products) use (&$processedProducts, &$updatedProducts, $progressBar, $now, $windowStart) {
                foreach ($products as $product) {
                    $processedProducts++;

                    // Total de unidades vendidas o consumidas en los últimos 12 meses
                    $isRestaurant = \App\Models\GeneralSetting::first()?->business_type === 'restaurant';
                    if ($isRestaurant) {
                        $totalSoldRaw = DB::table('inventory_movements')
                            ->where('product_id', $product->id)
                            ->where('quantity', '<', 0)
                            ->where('created_at', '>=', $windowStart)
                            ->sum('quantity');
                        $totalSold = $totalSoldRaw ? abs($totalSoldRaw) : 0;
                    } else {
                        $totalSold = DB::table('order_details')
                            ->join('orders', 'order_details.order_id', '=', 'orders.id')
                            ->where('order_details.product_id', $product->id)
                            ->where('orders.status', 'Completed')
                            ->where('orders.created_at', '>=', $windowStart)
                            ->sum('order_details.quantity');
                    }

                    if ($totalSold === null || $totalSold == 0) {
                        $salesAverage = 0;
                    } else {
                        // Fecha del primer ingreso a inventario (lotes o movimientos)
                        $firstStockDate = DB::table('product_lots')
                            ->where('product_id', $product->id)
                            ->min('created_at');

                        if (!$firstStockDate) {
                            $firstStockDate = DB::table('inventory_movements')
                                ->where('product_id', $product->id)
                                ->where('quantity', '>', 0)
                                ->min('created_at');
                        }

                        $firstSaleDate = DB::table('order_details')
                            ->join('orders', 'order_details.order_id', '=', 'orders.id')
                            ->where('order_details.product_id', $product->id)
                            ->where('orders.status', 'Completed')
                            ->where('orders.created_at', '>=', $windowStart)
                            ->min('orders.created_at');

                        $possibleDates = array_filter([
                            $firstStockDate ? Carbon::parse($firstStockDate) : null,
                            $firstSaleDate ? Carbon::parse($firstSaleDate) : null,
                            $product->created_at ? Carbon::parse($product->created_at) : null,
                        ]);

                        if (!empty($possibleDates)) {
                            $earliestDate = collect($possibleDates)->min();
                            $referenceDate = $earliestDate->isBefore($windowStart) ? $windowStart->copy() : $earliestDate;
                        } else {
                            $referenceDate = $now->copy()->subMonths(12);
                        }

                        // Calcular días transcurridos y convertir a meses reales (30.44 días por mes)
                        $daysElapsed = max(1, $referenceDate->diffInDays($now));
                        $monthsOfLife = (int) ceil($daysElapsed / 30.4375);
                        $actualMonths = max(1, min(12, $monthsOfLife));

                        // Promedio mensual = ventas en ventana / meses reales transcurridos
                        $salesAverage = round($totalSold / $actualMonths, 2);
                    }

                    $product->update([
                        'sales_average'            => $salesAverage,
                        'sales_average_updated_at' => $now,
                    ]);
                    $updatedProducts++;

                    $progressBar->advance();
                }
            });

            DB::commit();

            $progressBar->finish();
            $this->newLine(2);
            $this->info('Promedio de ventas actualizado. Calculando factores estacionales...');

            // Feature 1: Calcular factores estacionales automáticos por grupo
            $this->calcularFactoresEstacionales($now);

            $this->info('Cálculo completado exitosamente!');
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

    /**
     * Calcula y persiste factores estacionales por grupo y mes.
     * Compara el promedio de ventas de cada mes contra el promedio general
     * de los últimos 2 años para determinar si hay estacionalidad.
     */
    private function calcularFactoresEstacionales(Carbon $now): void
    {
        $this->info('Calculando factores estacionales automáticos...');

        $dosAnosAtras = $now->copy()->subYears(2)->startOfMonth();

        // Obtener ventas mensuales por grupo de los últimos 2 años
        $ventasPorGrupoMes = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->join('products', 'products.id', '=', 'order_details.product_id')
            ->select(
                'products.group_id',
                DB::raw('MONTH(orders.created_at) AS mes'),
                DB::raw('YEAR(orders.created_at)  AS anio'),
                DB::raw('SUM(order_details.quantity) AS total_ventas')
            )
            ->where('orders.status', 'Completed')
            ->where('orders.created_at', '>=', $dosAnosAtras)
            ->whereNotNull('products.group_id')
            ->groupBy('products.group_id', 'mes', 'anio')
            ->get();

        if ($ventasPorGrupoMes->isEmpty()) {
            $this->warn('No hay ventas suficientes para calcular estacionalidad.');
            return;
        }

        // Agrupar por group_id para calcular promedio global y promedio por mes
        $porGrupo = $ventasPorGrupoMes->groupBy('group_id');

        $factoresCalculados = 0;

        foreach ($porGrupo as $groupId => $registros) {
            // Promedio mensual global del grupo (todas las ventas / cantidad de meses distintos)
            $totalVentas  = $registros->sum('total_ventas');
            $mesesDistintos = $registros->unique(fn($r) => $r->anio . '-' . $r->mes)->count();

            if ($mesesDistintos === 0 || $totalVentas == 0) continue;

            $promedioGlobal = $totalVentas / $mesesDistintos;

            // Calcular el promedio por cada mes del año (1-12) acumulando años
            $porMes = $registros->groupBy('mes');

            for ($mes = 1; $mes <= 12; $mes++) {
                $ventasMes   = $porMes->get($mes);
                $promedioMes = $ventasMes ? $ventasMes->avg('total_ventas') : 0;

                // Factor = promedio del mes / promedio global
                // Si el promedio global es 0, factor = 1 (sin ajuste)
                $factor = $promedioGlobal > 0
                    ? round($promedioMes / $promedioGlobal, 2)
                    : 1.00;

                // Limitar factor entre 0.5 y 3.0 para evitar extremos
                $factor = max(0.50, min(3.00, $factor));

                // Upsert: actualizar si existe, crear si no
                DB::table('product_seasonal_factors')->upsert(
                    [
                        'group_id'   => $groupId,
                        'month'      => $mes,
                        'factor'     => $factor,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                    ['group_id', 'month'], // columnas únicas
                    ['factor', 'updated_at']
                );

                $factoresCalculados++;
            }
        }

        $this->info("Factores estacionales actualizados: {$factoresCalculados} registros.");
    }
}
