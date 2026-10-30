<?php

declare(strict_types=1);

namespace App\Services\Wholesale;

use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Servicio para Inteligencia y Comparación Competitiva de Precios en Droguerías B2B.
 * Cruza anónimamente el inventario propio contra todas las droguerías de product_suppliers en el Master.
 */
class WholesaleMarketBenchmarkService
{
    /**
     * Obtiene el nombre de la conexión a la base de datos central/Master.
     */
    protected function getCentralConnectionName(): string
    {
        $isTenant = function_exists('tenancy') && tenancy()->initialized;

        if ($isTenant) {
            return config('tenancy.database.central_connection', 'mysql');
        }

        return DB::getDefaultConnection();
    }

    /**
     * Consulta y consolida todas las ofertas del mercado desde product_suppliers en el Master.
     * Retorna una colección indexada por código de barras normalizado con métricas anónimas.
     *
     * @return Collection<string, object>
     */
    public function getAggregatedMarketOffers(): Collection
    {
        $centralConnection = $this->getCentralConnectionName();
        $isTenant = function_exists('tenancy') && tenancy()->initialized;
        $localConnection = $isTenant ? DB::getDefaultConnection() : null;

        // 1. Obtener agregación del Master
        $queryMaster = DB::connection($centralConnection)
            ->table('product_suppliers')
            ->whereNotNull('barcode_match')
            ->whereRaw("TRIM(barcode_match) != ''")
            ->where(function ($q) {
                $q->where('unit_cost_usd', '>', 0)
                  ->orWhere('unit_cost_usd_with_discount', '>', 0);
            })
            ->select(
                DB::raw("TRIM(barcode_match) as barcode"),
                DB::raw("MIN(CASE WHEN unit_cost_usd_with_discount > 0 THEN unit_cost_usd_with_discount ELSE unit_cost_usd END) as best_market_price_usd"),
                DB::raw("AVG(CASE WHEN unit_cost_usd_with_discount > 0 THEN unit_cost_usd_with_discount ELSE unit_cost_usd END) as avg_market_price_usd"),
                DB::raw("MAX(CASE WHEN unit_cost_usd_with_discount > 0 THEN unit_cost_usd_with_discount ELSE unit_cost_usd END) as max_market_price_usd"),
                DB::raw("GROUP_CONCAT(DISTINCT (CASE WHEN unit_cost_usd_with_discount > 0 THEN unit_cost_usd_with_discount ELSE unit_cost_usd END) ORDER BY (CASE WHEN unit_cost_usd_with_discount > 0 THEN unit_cost_usd_with_discount ELSE unit_cost_usd END) ASC SEPARATOR ',') as sorted_prices"),
                DB::raw("COUNT(DISTINCT supplier_id) as competitors_count"),
                DB::raw("SUM(COALESCE(quantity, 0)) as market_total_stock"),
                DB::raw("MAX(name) as market_product_name"),
                DB::raw("MAX(laboratory) as market_laboratory")
            )
            ->groupBy(DB::raw("TRIM(barcode_match)"));

        $masterItems = $queryMaster->get()->keyBy('barcode');

        // Extraer mejor y segundo mejor precio del mercado
        foreach ($masterItems as $mItem) {
            $prices = array_map('floatval', array_filter(explode(',', (string) ($mItem->sorted_prices ?? ''))));
            sort($prices, SORT_NUMERIC);
            $mItem->best_market_price_usd = $prices[0] ?? (float) $mItem->best_market_price_usd;
            $mItem->second_best_market_price_usd = isset($prices[1]) ? (float) $prices[1] : null;
        }

        // 2. Si estamos en un tenant y la conexión local es distinta, consolidar con ofertas locales
        if ($localConnection && $localConnection !== $centralConnection) {
            try {
                $localItems = DB::connection($localConnection)
                    ->table('product_suppliers')
                    ->whereNotNull('barcode_match')
                    ->whereRaw("TRIM(barcode_match) != ''")
                    ->where(function ($q) {
                        $q->where('unit_cost_usd', '>', 0)
                          ->orWhere('unit_cost_usd_with_discount', '>', 0);
                    })
                    ->select(
                        DB::raw("TRIM(barcode_match) as barcode"),
                        DB::raw("MIN(CASE WHEN unit_cost_usd_with_discount > 0 THEN unit_cost_usd_with_discount ELSE unit_cost_usd END) as best_market_price_usd"),
                        DB::raw("AVG(CASE WHEN unit_cost_usd_with_discount > 0 THEN unit_cost_usd_with_discount ELSE unit_cost_usd END) as avg_market_price_usd"),
                        DB::raw("MAX(CASE WHEN unit_cost_usd_with_discount > 0 THEN unit_cost_usd_with_discount ELSE unit_cost_usd END) as max_market_price_usd"),
                        DB::raw("GROUP_CONCAT(DISTINCT (CASE WHEN unit_cost_usd_with_discount > 0 THEN unit_cost_usd_with_discount ELSE unit_cost_usd END) ORDER BY (CASE WHEN unit_cost_usd_with_discount > 0 THEN unit_cost_usd_with_discount ELSE unit_cost_usd END) ASC SEPARATOR ',') as sorted_prices"),
                        DB::raw("COUNT(DISTINCT supplier_id) as competitors_count"),
                        DB::raw("SUM(COALESCE(quantity, 0)) as market_total_stock"),
                        DB::raw("MAX(name) as market_product_name"),
                        DB::raw("MAX(laboratory) as market_laboratory")
                    )
                    ->groupBy(DB::raw("TRIM(barcode_match)"))
                    ->get()
                    ->keyBy('barcode');

                foreach ($localItems as $barcode => $lItem) {
                    $lPrices = array_map('floatval', array_filter(explode(',', (string) ($lItem->sorted_prices ?? ''))));
                    sort($lPrices, SORT_NUMERIC);
                    $lItem->second_best_market_price_usd = isset($lPrices[1]) ? (float) $lPrices[1] : null;

                    if (!$masterItems->has($barcode)) {
                        $masterItems->put($barcode, $lItem);
                    } else {
                        $mItem = $masterItems->get($barcode);
                        $combinedPrices = array_unique(array_merge(
                            array_map('floatval', array_filter(explode(',', (string) ($mItem->sorted_prices ?? '')))),
                            $lPrices
                        ));
                        sort($combinedPrices, SORT_NUMERIC);

                        $mItem->best_market_price_usd = $combinedPrices[0] ?? min((float) $mItem->best_market_price_usd, (float) $lItem->best_market_price_usd);
                        $mItem->second_best_market_price_usd = isset($combinedPrices[1]) ? (float) $combinedPrices[1] : null;
                        $mItem->avg_market_price_usd = ((float) $mItem->avg_market_price_usd + (float) $lItem->avg_market_price_usd) / 2;
                        $mItem->max_market_price_usd = max((float) $mItem->max_market_price_usd, (float) $lItem->max_market_price_usd);
                        $mItem->competitors_count = max((int) $mItem->competitors_count, (int) $lItem->competitors_count);
                        $mItem->market_total_stock = (float) $mItem->market_total_stock + (float) $lItem->market_total_stock;
                    }
                }
            } catch (\Throwable) {
            }
        }

        return $masterItems;
    }

    /**
     * Construye y clasifica la totalidad de elementos del benchmark entre inventario propio y mercado.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function computeBenchmarkDataset(): Collection
    {
        $marketOffers = $this->getAggregatedMarketOffers();

        // Obtener productos locales con sus laboratorios
        $localProducts = DB::table('products')
            ->leftJoin('laboratories', 'laboratories.id', '=', 'products.laboratory_id')
            ->where(function ($q) {
                $q->whereNull('products.is_deleted')
                  ->orWhere('products.is_deleted', 0);
            })
            ->select(
                'products.id',
                'products.barcode',
                'products.name',
                'products.unit_cost',
                'products.sale_price',
                'products.stock',
                'products.laboratory_id',
                'laboratories.name as laboratory_name'
            )
            ->get();

        $dataset = collect();
        $processedBarcodes = [];

        // 1. Evaluar productos propios contra el mercado
        foreach ($localProducts as $product) {
            $rawBarcode = trim((string) ($product->barcode ?? ''));
            if ($rawBarcode !== '') {
                $processedBarcodes[$rawBarcode] = true;
            }

            $costUsd = (float) ($product->unit_cost ?? 0);
            $priceUsd = (float) ($product->sale_price ?: $costUsd);
            $stock = (float) ($product->stock ?? 0);

            $marketItem = $rawBarcode !== '' ? $marketOffers->get($rawBarcode) : null;

            if ($marketItem) {
                $bestMarket = (float) $marketItem->best_market_price_usd;
                $secondBestMarket = isset($marketItem->second_best_market_price_usd) && $marketItem->second_best_market_price_usd !== null
                    ? (float) $marketItem->second_best_market_price_usd
                    : null;
                $avgMarket = (float) $marketItem->avg_market_price_usd;
                $maxMarket = (float) $marketItem->max_market_price_usd;
                $competitorsCount = (int) $marketItem->competitors_count;
                $marketStock = (float) $marketItem->market_total_stock;

                if ($stock > 0) {
                    if ($priceUsd <= $bestMarket) {
                        // Más barato o igual (Competitivo)
                        // Si existe un 2do mejor precio en el mercado mayor al mejor, calculamos la ventaja con referencia a ese 2do competidor
                        $referencePrice = ($secondBestMarket !== null && $secondBestMarket > $priceUsd) ? $secondBestMarket : $bestMarket;
                        $diffUsd = max(0.0, $referencePrice - $priceUsd);
                        $diffPct = $referencePrice > 0 ? ($diffUsd / $referencePrice) * 100 : 0.0;
                        $marginPct = $costUsd > 0 ? (($priceUsd - $costUsd) / $costUsd) * 100 : 0.0;

                        $dataset->push([
                            'barcode' => $rawBarcode,
                            'product_id' => (int) $product->id,
                            'name' => (string) $product->name,
                            'laboratory_id' => $product->laboratory_id ? (int) $product->laboratory_id : null,
                            'laboratory_name' => (string) ($product->laboratory_name ?? 'N/A'),
                            'our_cost_usd' => $costUsd,
                            'our_price_usd' => $priceUsd,
                            'our_stock' => $stock,
                            'our_margin_pct' => $marginPct,
                            'best_market_price_usd' => $bestMarket,
                            'second_best_market_price_usd' => $secondBestMarket,
                            'reference_market_price_usd' => $referencePrice,
                            'avg_market_price_usd' => $avgMarket,
                            'max_market_price_usd' => $maxMarket,
                            'price_diff_usd' => $diffUsd,
                            'price_diff_pct' => $diffPct,
                            'competitors_count' => $competitorsCount,
                            'market_total_stock' => $marketStock,
                            'segment' => 'cheaper',
                            'coverage_sub_type' => null,
                            'is_below_our_cost' => false,
                        ]);
                    } else {
                        // Más caro (Fuera de precio)
                        $gapUsd = $priceUsd - $bestMarket;
                        $gapPct = $bestMarket > 0 ? ($gapUsd / $bestMarket) * 100 : 0.0;
                        $marginPct = $costUsd > 0 ? (($priceUsd - $costUsd) / $costUsd) * 100 : 0.0;
                        $isBelowCost = $bestMarket < $costUsd;

                        $dataset->push([
                            'barcode' => $rawBarcode,
                            'product_id' => (int) $product->id,
                            'name' => (string) $product->name,
                            'laboratory_id' => $product->laboratory_id ? (int) $product->laboratory_id : null,
                            'laboratory_name' => (string) ($product->laboratory_name ?? 'N/A'),
                            'our_cost_usd' => $costUsd,
                            'our_price_usd' => $priceUsd,
                            'our_stock' => $stock,
                            'our_margin_pct' => $marginPct,
                            'best_market_price_usd' => $bestMarket,
                            'second_best_market_price_usd' => $secondBestMarket,
                            'reference_market_price_usd' => $bestMarket,
                            'avg_market_price_usd' => $avgMarket,
                            'max_market_price_usd' => $maxMarket,
                            'price_diff_usd' => $gapUsd,
                            'price_diff_pct' => $gapPct,
                            'competitors_count' => $competitorsCount,
                            'market_total_stock' => $marketStock,
                            'segment' => 'more_expensive',
                            'coverage_sub_type' => null,
                            'is_below_our_cost' => $isBelowCost,
                        ]);
                    }
                } else {
                    // Creado pero sin stock local
                    $dataset->push([
                        'barcode' => $rawBarcode,
                        'product_id' => (int) $product->id,
                        'name' => (string) $product->name,
                        'laboratory_id' => $product->laboratory_id ? (int) $product->laboratory_id : null,
                        'laboratory_name' => (string) ($product->laboratory_name ?? 'N/A'),
                        'our_cost_usd' => $costUsd,
                        'our_price_usd' => $priceUsd,
                        'our_stock' => 0.0,
                        'our_margin_pct' => null,
                        'best_market_price_usd' => $bestMarket,
                        'second_best_market_price_usd' => $secondBestMarket,
                        'reference_market_price_usd' => $bestMarket,
                        'avg_market_price_usd' => $avgMarket,
                        'max_market_price_usd' => $maxMarket,
                        'price_diff_usd' => 0.0,
                        'price_diff_pct' => 0.0,
                        'competitors_count' => $competitorsCount,
                        'market_total_stock' => $marketStock,
                        'segment' => 'uncovered',
                        'coverage_sub_type' => 'zero_stock',
                        'is_below_our_cost' => false,
                    ]);
                }
            }
        }

        // 2. Evaluar productos del mercado no creados en nuestro catálogo
        foreach ($marketOffers as $barcode => $marketItem) {
            if (!isset($processedBarcodes[$barcode])) {
                $bestMarket = (float) $marketItem->best_market_price_usd;
                $secondBestMarket = isset($marketItem->second_best_market_price_usd) && $marketItem->second_best_market_price_usd !== null
                    ? (float) $marketItem->second_best_market_price_usd
                    : null;
                $avgMarket = (float) $marketItem->avg_market_price_usd;
                $maxMarket = (float) $marketItem->max_market_price_usd;
                $competitorsCount = (int) $marketItem->competitors_count;
                $marketStock = (float) $marketItem->market_total_stock;

                $dataset->push([
                    'barcode' => (string) $barcode,
                    'product_id' => null,
                    'name' => (string) ($marketItem->market_product_name ?? 'Producto de Mercado'),
                    'laboratory_id' => null,
                    'laboratory_name' => (string) ($marketItem->market_laboratory ?? 'Mercado'),
                    'our_cost_usd' => null,
                    'our_price_usd' => null,
                    'our_stock' => 0.0,
                    'our_margin_pct' => null,
                    'best_market_price_usd' => $bestMarket,
                    'second_best_market_price_usd' => $secondBestMarket,
                    'reference_market_price_usd' => $bestMarket,
                    'avg_market_price_usd' => $avgMarket,
                    'max_market_price_usd' => $maxMarket,
                    'price_diff_usd' => 0.0,
                    'price_diff_pct' => 0.0,
                    'competitors_count' => $competitorsCount,
                    'market_total_stock' => $marketStock,
                    'segment' => 'uncovered',
                    'coverage_sub_type' => 'not_created',
                    'is_below_our_cost' => false,
                ]);
            }
        }

        return $dataset;
    }

    /**
     * Calcula los KPIs consolidados del dashboard para el semáforo y tarjetas resumen.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getBenchmarkSummary(array $filters = []): array
    {
        $dataset = $this->computeBenchmarkDataset();

        $cheaper = $dataset->where('segment', 'cheaper');
        $moreExpensive = $dataset->where('segment', 'more_expensive');
        $uncovered = $dataset->where('segment', 'uncovered');

        $cheaperCount = $cheaper->count();
        $moreExpensiveCount = $moreExpensive->count();
        $uncoveredCount = $uncovered->count();

        $activeEvaluated = $cheaperCount + $moreExpensiveCount;
        $cheaperPct = $activeEvaluated > 0 ? round(($cheaperCount / $activeEvaluated) * 100, 1) : 0.0;
        $moreExpensivePct = $activeEvaluated > 0 ? round(($moreExpensiveCount / $activeEvaluated) * 100, 1) : 0.0;

        $avgOverpriceGapPct = $moreExpensiveCount > 0 
            ? round((float) $moreExpensive->avg('price_diff_pct'), 1) 
            : 0.0;

        // Mediana del sobreprecio para evitar distorsiones por valores atípicos
        $moreExpensivePcts = $moreExpensive->pluck('price_diff_pct')->sort()->values();
        $moreExpensiveTotal = $moreExpensivePcts->count();
        if ($moreExpensiveTotal === 0) {
            $medianOverpriceGapPct = 0.0;
        } elseif ($moreExpensiveTotal % 2 === 1) {
            $medianOverpriceGapPct = round((float) $moreExpensivePcts->get(intdiv($moreExpensiveTotal, 2)), 1);
        } else {
            $mid = intdiv($moreExpensiveTotal, 2);
            $medianOverpriceGapPct = round(((float) $moreExpensivePcts->get($mid - 1) + (float) $moreExpensivePcts->get($mid)) / 2, 1);
        }

        $notCreatedCount = $uncovered->where('coverage_sub_type', 'not_created')->count();
        $zeroStockCount = $uncovered->where('coverage_sub_type', 'zero_stock')->count();

        // Productos de alta demanda no cubiertos: >= 3 droguerías ofertantes y stock de mercado > 0
        $highDemandUncoveredCount = $uncovered->filter(function ($item) {
            return ($item['competitors_count'] ?? 0) >= 3 && ($item['market_total_stock'] ?? 0) > 0;
        })->count();

        return [
            'total_market_products' => $this->getAggregatedMarketOffers()->count(),
            'our_active_evaluated_count' => $activeEvaluated,
            'comparable_total' => $activeEvaluated,
            'cheaper_count' => $cheaperCount,
            'cheaper_pct' => $cheaperPct,
            'more_expensive_count' => $moreExpensiveCount,
            'more_expensive_pct' => $moreExpensivePct,
            'avg_overprice_gap_pct' => $avgOverpriceGapPct,
            'median_overprice_gap_pct' => $medianOverpriceGapPct,
            'uncovered_count' => $uncoveredCount,
            'uncovered_not_created_count' => $notCreatedCount,
            'uncovered_zero_stock_count' => $zeroStockCount,
            'high_demand_uncovered_count' => $highDemandUncoveredCount,
            'competitiveness_index' => $activeEvaluated > 0 ? round(($cheaperCount / $activeEvaluated) * 100, 1) : 0.0,
        ];
    }

    /**
     * Retorna los elementos paginados y filtrados para la tabla interactiva de cada segmento.
     *
     * @param string $segment
     * @param array<string, mixed> $filters
     * @param int $perPage
     * @param int $page
     * @return LengthAwarePaginator
     */
    public function getPaginatedBenchmarkItems(
        string $segment,
        array $filters = [],
        int $perPage = 15,
        int $page = 1
    ): LengthAwarePaginator {
        $dataset = $this->computeBenchmarkDataset();

        // 1. Filtrar por segmento obligatorio
        $filtered = $dataset->where('segment', $segment);

        // 2. Filtro de búsqueda (código de barras, nombre o laboratorio)
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $searchLower = mb_strtolower($search);
            $filtered = $filtered->filter(function ($item) use ($searchLower) {
                return str_contains(mb_strtolower((string) $item['barcode']), $searchLower)
                    || str_contains(mb_strtolower((string) $item['name']), $searchLower)
                    || str_contains(mb_strtolower((string) $item['laboratory_name']), $searchLower);
            });
        }

        // 3. Filtro por laboratorio
        $labId = $filters['laboratory_id'] ?? null;
        if ($labId !== null && (int) $labId > 0) {
            $filtered = $filtered->where('laboratory_id', (int) $labId);
        }

        // 4. Sub-filtro para no cobertura (all, not_created, zero_stock)
        $coverageType = $filters['coverage_type'] ?? 'all';
        if ($segment === 'uncovered' && in_array($coverageType, ['not_created', 'zero_stock'], true)) {
            $filtered = $filtered->where('coverage_sub_type', $coverageType);
        }

        // 5. Filtros de rango de diferencia porcentual
        if (isset($filters['price_diff_min']) && is_numeric($filters['price_diff_min'])) {
            $min = (float) $filters['price_diff_min'];
            $filtered = $filtered->where('price_diff_pct', '>=', $min);
        }
        if (isset($filters['price_diff_max']) && is_numeric($filters['price_diff_max'])) {
            $max = (float) $filters['price_diff_max'];
            $filtered = $filtered->where('price_diff_pct', '<=', $max);
        }

        // 6. Filtro de mínimo de competidores
        if (isset($filters['min_competitors']) && is_numeric($filters['min_competitors'])) {
            $minComp = (int) $filters['min_competitors'];
            $filtered = $filtered->where('competitors_count', '>=', $minComp);
        }

        // 7. Ordenamiento semántico según el segmento
        if ($segment === 'cheaper') {
            $sorted = $filtered->sortByDesc('price_diff_pct')->values();
        } elseif ($segment === 'more_expensive') {
            $sorted = $filtered->sortByDesc('price_diff_pct')->values();
        } else {
            // Uncovered: productos más masivos en competidores y con mayor stock primero
            $sorted = $filtered->sort(function ($a, $b) {
                if ($a['competitors_count'] === $b['competitors_count']) {
                    return $b['market_total_stock'] <=> $a['market_total_stock'];
                }
                return $b['competitors_count'] <=> $a['competitors_count'];
            })->values();
        }

        $total = $sorted->count();
        $offset = ($page - 1) * $perPage;
        $items = $sorted->slice($offset, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()]
        );
    }

    /**
     * Ajuste rápido de precio mayorista para un producto propio desde el dashboard.
     *
     * @param int $productId
     * @param float $newPriceUsd
     * @return array<string, mixed>
     */
    public function updateProductPrice(int $productId, float $newPriceUsd): array
    {
        $product = Product::findOrFail($productId);
        $oldPrice = (float) ($product->sale_price ?: $product->unit_cost);

        $product->update([
            'sale_price' => $newPriceUsd,
        ]);

        return [
            'success' => true,
            'message' => "Precio del producto {$product->name} actualizado de \${$oldPrice} a \${$newPriceUsd} USD.",
            'product_id' => $product->id,
            'old_price_usd' => $oldPrice,
            'new_price_usd' => $newPriceUsd,
        ];
    }
}
