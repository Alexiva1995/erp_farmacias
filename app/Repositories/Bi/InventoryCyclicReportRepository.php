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
        $categoryId = $filters['category_id'] ?? null;

        $query = DB::table('product_counts')
            ->join('products', 'products.id', '=', 'product_counts.product_id')
            ->whereIn('product_counts.status', ['approved', 'pending'])
            ->whereBetween('product_counts.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if (!empty($categoryId)) {
            $query->where('products.category_id', $categoryId);
        }

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
            'missing_loss_value' => round((float)($stats->total_missing_value ?? 0), 2),
            'surplus_gain_value' => round((float)($stats->total_surplus_value ?? 0), 2),
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
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');
        $categoryId = $filters['category_id'] ?? null;

        $query = DB::table('product_counts')
            ->join('products', 'products.id', '=', 'product_counts.product_id')
            ->whereIn('product_counts.status', ['approved', 'pending'])
            ->whereBetween('product_counts.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if (!empty($categoryId)) {
            $query->where('products.category_id', $categoryId);
        }

        $results = $query->select(
                DB::raw('DATE_FORMAT(product_counts.created_at, "%Y-%m") as month'),
                DB::raw('SUM(CASE WHEN discrepancy < 0 THEN ABS(discrepancy) ELSE 0 END) as missing'),
                DB::raw('SUM(CASE WHEN discrepancy > 0 THEN discrepancy ELSE 0 END) as surplus'),
                DB::raw('SUM(discrepancy * -1 * products.unit_cost) as financial_impact')
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
        $categoryId = $filters['category_id'] ?? null;

        $baseQuery = DB::table('product_counts')
            ->join('products', 'products.id', '=', 'product_counts.product_id')
            ->whereIn('product_counts.status', ['approved', 'pending'])
            ->whereBetween('product_counts.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select(
                'products.name',
                'product_counts.discrepancy',
                DB::raw('ABS(product_counts.discrepancy) * products.unit_cost as impact_value')
            );

        if (!empty($categoryId)) {
            $baseQuery->where('products.category_id', $categoryId);
        }

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
        $categoryId = $filters['category_id'] ?? null;

        $query = DB::table('product_counts')
            ->join('products', 'products.id', '=', 'product_counts.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('product_counts.status', ['approved', 'pending'])
            ->whereBetween('product_counts.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if (!empty($categoryId)) {
            $query->where('products.category_id', $categoryId);
        }

        return $query->select(
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
     * Algoritmo inteligente de detección de cruce de códigos (sustituciones)
     */
    public function getCodeCrossing(array $filters): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->format('Y-m-d');
        $categoryId = $filters['category_id'] ?? null;

        $query = DB::table('product_counts')
            ->join('products', 'products.id', '=', 'product_counts.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('product_counts.status', ['approved', 'pending'])
            ->where('discrepancy', '!=', 0)
            ->whereBetween('product_counts.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if (!empty($categoryId)) {
            $query->where('products.category_id', $categoryId);
        }

        $counts = $query->select(
                'products.id',
                'products.name',
                'products.active_ingredient',
                'products.barcode',
                'products.laboratory_id',
                'products.category_id',
                'categories.name as category_name',
                'product_counts.discrepancy',
                'product_counts.cycle_id',
                'product_counts.created_at'
            )
            ->get();

        $candidates = [];

        // Evaluar compatibilidad de pares (faltante vs sobrante)
        foreach ($counts as $a) {
            if ($a->discrepancy >= 0) {
                continue; // Producto A es el faltante
            }

            foreach ($counts as $b) {
                if ($b->discrepancy <= 0 || $a->id === $b->id) {
                    continue; // Producto B es el sobrante
                }

                // 1. Simetría de cantidades
                $isSymmetric = abs((float)$a->discrepancy) === abs((float)$b->discrepancy);
                if (!$isSymmetric) {
                    continue;
                }

                $score = 0;
                $reasons = [];

                // 2. Coincidencia de Principio Activo
                $ingA = mb_strtolower(trim((string)$a->active_ingredient));
                $ingB = mb_strtolower(trim((string)$b->active_ingredient));

                if (!empty($ingA) && !empty($ingB)) {
                    if ($ingA === $ingB) {
                        $score += 50;
                        $reasons[] = 'Mismo Principio Activo';
                    } elseif (str_contains($ingA, $ingB) || str_contains($ingB, $ingA)) {
                        $score += 40;
                        $reasons[] = 'Principio Activo Equivalente';
                    }
                }

                // 3. Similitud de Nombre del Producto
                similar_text(mb_strtolower((string)$a->name), mb_strtolower((string)$b->name), $simName);
                if ($simName >= 75) {
                    $score += 35;
                    $reasons[] = 'Nombre Muy Similar (' . round($simName) . '%)';
                } elseif ($simName >= 50) {
                    $score += 20;
                    $reasons[] = 'Nombre Parcial (' . round($simName) . '%)';
                }

                // 4. Mismo Ciclo / Sesión de Conteo
                if (!empty($a->cycle_id) && !empty($b->cycle_id) && $a->cycle_id === $b->cycle_id) {
                    $score += 25;
                    $reasons[] = 'Mismo Ciclo de Conteo';
                }

                // 5. Misma Fecha de Auditoría
                $dateA = substr((string)$a->created_at, 0, 10);
                $dateB = substr((string)$b->created_at, 0, 10);
                if ($dateA === $dateB) {
                    $score += 15;
                    $reasons[] = 'Misma Fecha de Conteo';
                }

                // 6. Código de Barras Cercano / Adyacente
                $barA = trim((string)$a->barcode);
                $barB = trim((string)$b->barcode);
                if (!empty($barA) && !empty($barB) && strlen($barA) >= 6 && strlen($barB) >= 6) {
                    if (levenshtein($barA, $barB) <= 2) {
                        $score += 25;
                        $reasons[] = 'Código de Barras Casi Idéntico';
                    } elseif (substr($barA, 0, 6) === substr($barB, 0, 6)) {
                        $score += 15;
                        $reasons[] = 'Mismo Prefijo de Barra';
                    }
                }

                // 7. Misma Categoría o Laboratorio
                if ($a->category_id === $b->category_id) {
                    $score += 10;
                }
                if (!empty($a->laboratory_id) && $a->laboratory_id === $b->laboratory_id) {
                    $score += 10;
                    $reasons[] = 'Mismo Laboratorio';
                }

                // Umbral mínimo de confianza para evitar falsos positivos
                if ($score >= 40) {
                    $confidence = 'Media (Simetría en Ciclo)';
                    if ($score >= 75) {
                        $confidence = 'Muy Alta (' . ($reasons[0] ?? 'Molécula/Ciclo') . ')';
                    } elseif ($score >= 55) {
                        $confidence = 'Alta (' . ($reasons[0] ?? 'Similitud') . ')';
                    }

                    $candidates[] = [
                        'id_a' => $a->id,
                        'id_b' => $b->id,
                        'score' => $score,
                        'category' => $a->category_name,
                        'product_a' => $a->name,
                        'active_ingredient_a' => $a->active_ingredient,
                        'discrepancy_a' => $a->discrepancy,
                        'product_b' => $b->name,
                        'active_ingredient_b' => $b->active_ingredient,
                        'discrepancy_b' => $b->discrepancy,
                        'confidence' => $confidence,
                        'match_reason' => implode(' + ', $reasons),
                    ];
                }
            }
        }

        // Ordenar candidatos por mayor score para asignar las mejores coincidencias primero
        usort($candidates, fn($x, $y) => $y['score'] <=> $x['score']);

        $substitutions = [];
        $processedA = [];
        $processedB = [];

        foreach ($candidates as $cand) {
            if (in_array($cand['id_a'], $processedA, true) || in_array($cand['id_b'], $processedB, true)) {
                continue;
            }

            $substitutions[] = [
                'category' => $cand['category'],
                'product_a' => $cand['product_a'],
                'active_ingredient_a' => $cand['active_ingredient_a'] ?? '',
                'discrepancy_a' => $cand['discrepancy_a'],
                'product_b' => $cand['product_b'],
                'active_ingredient_b' => $cand['active_ingredient_b'] ?? '',
                'discrepancy_b' => $cand['discrepancy_b'],
                'confidence' => $cand['confidence'],
                'match_reason' => $cand['match_reason'],
            ];

            $processedA[] = $cand['id_a'];
            $processedB[] = $cand['id_b'];
        }

        return $substitutions;
    }
}
