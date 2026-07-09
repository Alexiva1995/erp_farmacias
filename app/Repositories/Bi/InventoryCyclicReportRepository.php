<?php

declare(strict_types=1);

namespace App\Repositories\Bi;

use Illuminate\Support\Facades\DB;
use App\Models\ProductCount;

class InventoryCyclicReportRepository
{
    /**
     * Obtiene los KPIs principales de inventario cíclico
     */
    public function getKpis(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        $query = DB::table('product_counts')
            ->join('products', 'products.id', '=', 'product_counts.product_id')
            ->whereIn('product_counts.status', ['approved', 'pending'])
            ->whereBetween('product_counts.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        $totalCounted = (clone $query)->count();
        $noDifferenceCount = (clone $query)->where('discrepancy', 0)->count();
        
        $stats = (clone $query)->select(
            DB::raw('SUM(CASE WHEN discrepancy < 0 THEN ABS(discrepancy) ELSE 0 END) as total_missing_qty'),
            DB::raw('SUM(CASE WHEN discrepancy > 0 THEN discrepancy ELSE 0 END) as total_surplus_qty'),
            DB::raw('SUM(CASE WHEN discrepancy < 0 THEN ABS(discrepancy) * products.unit_cost ELSE 0 END) as total_missing_value'),
            DB::raw('SUM(CASE WHEN discrepancy > 0 THEN discrepancy * products.unit_cost ELSE 0 END) as total_surplus_value')
        )->first();

        $eri = $totalCounted > 0 ? ($noDifferenceCount / $totalCounted) * 100 : 100;
        $errorRate = $totalCounted > 0 ? (($totalCounted - $noDifferenceCount) / $totalCounted) * 100 : 0;
        $netLoss = ($stats->total_missing_value ?? 0) - ($stats->total_surplus_value ?? 0);

        return [
            'eri' => round($eri, 2),
            'net_loss' => round($netLoss, 2),
            'error_rate' => round($errorRate, 2),
            'total_missing_units' => (int)($stats->total_missing_qty ?? 0),
            'total_surplus_units' => (int)($stats->total_surplus_qty ?? 0),
            'total_counted_skus' => $totalCounted
        ];
    }

    /**
     * Obtiene las tendencias históricas de faltantes vs sobrantes
     */
    public function getTrends(array $filters): array
    {
        $results = DB::table('product_counts')
            ->whereIn('status', ['approved', 'pending'])
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(CASE WHEN discrepancy < 0 THEN ABS(discrepancy) ELSE 0 END) as missing'),
                DB::raw('SUM(CASE WHEN discrepancy > 0 THEN discrepancy ELSE 0 END) as surplus'),
                DB::raw('SUM(discrepancy * -1 * (SELECT unit_cost FROM products WHERE products.id = product_counts.product_id)) as financial_impact')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $results->toArray();
    }

    /**
     * Obtiene los productos con mayor desviación
     */
    public function getTopDeviations(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        $baseQuery = DB::table('product_counts')
            ->join('products', 'products.id', '=', 'product_counts.product_id')
            ->whereIn('product_counts.status', ['approved', 'pending'])
            ->whereBetween('product_counts.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                'products.name',
                'product_counts.discrepancy',
                DB::raw('ABS(product_counts.discrepancy) * products.unit_cost as impact_value')
            );

        $topMissing = (clone $baseQuery)
            ->where('discrepancy', '<', 0)
            ->orderBy('discrepancy', 'asc') // Más negativo es mayor faltante
            ->limit(10)
            ->get();

        $topSurplus = (clone $baseQuery)
            ->where('discrepancy', '>', 0)
            ->orderBy('discrepancy', 'desc')
            ->limit(10)
            ->get();

        return [
            'missing' => $topMissing,
            'surplus' => $topSurplus
        ];
    }

    /**
     * Obtiene la desviación agrupada por categoría
     */
    public function getCategoryDeviation(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        return DB::table('product_counts')
            ->join('products', 'products.id', '=', 'product_counts.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('product_counts.status', ['approved', 'pending'])
            ->whereBetween('product_counts.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                'categories.name',
                DB::raw('SUM(ABS(product_counts.discrepancy)) as total_deviation'),
                DB::raw('COUNT(*) as total_counts')
            )
            ->groupBy('categories.name')
            ->orderBy('total_deviation', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Algoritmo de detección de cruce de códigos (sustituciones)
     */
    public function getCodeCrossing(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');

        // Buscamos productos en la misma categoría donde uno tenga faltante y otro sobrante
        // Que la diferencia neta de ambos sea cercana a cero (opcional)
        // Pero primero busquemos los que tienen discrepancias simétricas
        
        $counts = DB::table('product_counts')
            ->join('products', 'products.id', '=', 'product_counts.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('product_counts.status', ['approved', 'pending'])
            ->where('discrepancy', '!=', 0)
            ->whereBetween('product_counts.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                'products.id',
                'products.name',
                'products.category_id',
                'categories.name as category_name',
                'product_counts.discrepancy'
            )
            ->get();

        $substitutions = [];
        $processed = [];

        foreach ($counts as $a) {
            if (in_array($a->id, $processed)) continue;

            foreach ($counts as $b) {
                if ($a->id === $b->id) continue;
                if (in_array($b->id, $processed)) continue;

                // Si están en la misma categoría y tienen discrepancias inversas
                if ($a->category_id === $b->category_id && abs($a->discrepancy) === abs($b->discrepancy) && ($a->discrepancy * $b->discrepancy) < 0) {
                    $substitutions[] = [
                        'category' => $a->category_name,
                        'product_a' => $a->name,
                        'discrepancy_a' => $a->discrepancy,
                        'product_b' => $b->name,
                        'discrepancy_b' => $b->discrepancy,
                        'confidence' => 'Alta (Simétrica)'
                    ];
                    $processed[] = $a->id;
                    $processed[] = $b->id;
                    break;
                }
            }
        }

        return $substitutions;
    }
}
