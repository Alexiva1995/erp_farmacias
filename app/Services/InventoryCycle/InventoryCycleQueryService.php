<?php

namespace App\Services\InventoryCycle;

use App\Models\InventoryCycle;
use App\Models\Product;
use App\Models\ProductCount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryCycleQueryService
{
    /**
     * Prepara la consulta base para los conteos de productos.
     */
    private function getBaseQuery(): Builder
    {
        return ProductCount::query()->select('product_counts.*')->with([
            'product' => function ($query) {
                $query->with(['lots', 'laboratory']);
            },
            'user',
            'supervisor',
            'cycle',
            'productLot',
        ]);
    }

    /**
     * Prepara la consulta base para productos (para la vista de inventario).
     */
    private function getProductsBaseQuery(): Builder
    {
        return Product::query()->with(['lots', 'laboratory', 'origin']);
    }

    /**
     * Aplica los filtros a la consulta de conteos.
     */
    private function applyFiltersToCount(Builder $query, array $filters): Builder
    {
        if (!empty($filters['q'])) {
            $searchTerm = "%{$filters['q']}%";
            $query->where(function ($subQuery) use ($searchTerm) {
                $subQuery->orWhereHas('product', function ($productQuery) use ($searchTerm) {
                    $productQuery->where('name', 'like', $searchTerm)
                        ->orWhere('active_ingredient', 'like', $searchTerm)
                        ->orWhere('barcode', 'like', $searchTerm)
                        ->orWhere('id', 'like', $searchTerm);
                });
            });
        }

        if (!empty($filters['laboratoryId'])) {
            $query->whereHas('product', function ($productQuery) use ($filters) {
                $productQuery->where('laboratory_id', $filters['laboratoryId']);
            });
        }

        if (!empty($filters['startDate'])) {
            $dateColumn = (isset($filters['is_history']) && $filters['is_history'])
                ? 'processed_at'
                : 'created_at';
            $query->where($dateColumn, '>=', $filters['startDate']);
        }

        if (!empty($filters['endDate'])) {
            $dateColumn = (isset($filters['is_history']) && $filters['is_history'])
                ? 'processed_at'
                : 'created_at';
            $query->where($dateColumn, '<=', $filters['endDate']);
        }

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        return $query;
    }

    /**
     * Aplica los filtros a la consulta de productos (para inventario).
     */
    private function applyFiltersToProducts(Builder $query, array $filters): Builder
    {
        if (!empty($filters['q'])) {
            $searchTerm = "%{$filters['q']}%";
            $query->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('name', 'like', $searchTerm)
                    ->orWhere('active_ingredient', 'like', $searchTerm)
                    ->orWhere('barcode', 'like', value: $searchTerm)
                    ->orWhere('id', 'like', $searchTerm);
            });
        }

        if (!empty($filters['laboratoryId'])) {
            $query->where('laboratory_id', $filters['laboratoryId']);
        }

        if (!empty($filters['originId'])) {
            $query->where('origin_id', $filters['originId']);
        }

        if (isset($filters['hasStock'])) {
            $hasStock = $filters['hasStock'];
            $query->whereHas('lots', function ($lotQuery) use ($hasStock) {
                $lotQuery->where('expiration_date', '>=', now()->startOfDay())
                    ->where('quantity', $hasStock ? '>' : '=', 0);
            });
        }

        if (!empty($filters['startDate']) || !empty($filters['endDate'])) {
            $query->whereHas('lots', function ($lotQuery) use ($filters) {
                if (!empty($filters['startDate'])) {
                    $lotQuery->where('expiration_date', '>=', $filters['startDate']);
                }
                if (!empty($filters['endDate'])) {
                    $lotQuery->where('expiration_date', '<=', $filters['endDate']);
                }
            });
        }

        return $query;
    }

    /**
     * Aplica la ordenación a la consulta de conteos.
     */
    private function applySortingToCount(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
        $query->select('product_counts.*');

        switch ($sortBy) {
            case 'product.name':
                return $query->join('products', 'product_counts.product_id', '=', 'products.id')
                    ->orderBy('products.name', $orderBy);

            case 'laboratory.name':
                return $query->join('products', 'product_counts.product_id', '=', 'products.id')
                    ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
                    ->orderBy('laboratories.name', $orderBy);

            case 'user.name':
                return $query->join('users', 'product_counts.user_id', '=', 'users.id')
                    ->orderBy('users.name', $orderBy);

            case 'counted_quantity':
            case 'system_quantity':
            case 'discrepancy':
            case 'final_quantity':
                return $query->orderBy("product_counts.{$sortBy}", $orderBy);

            case 'created_at':
            case 'processed_at':
                return $query->orderBy("product_counts.{$sortBy}", $orderBy);

            default:
                $defaultSortColumn = $query->getQuery()->wheres && in_array('confirmed', array_column($query->getQuery()->wheres, 'values'))
                    ? 'processed_at'
                    : 'created_at';
                return $query->orderBy($defaultSortColumn, 'desc');
        }
    }

    /**
     * Aplica la ordenación a la consulta de productos.
     */
    private function applySortingToProducts(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
        switch ($sortBy) {
            case 'laboratory.name':
                return $query->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
                    ->orderBy('laboratories.name', $orderBy)
                    ->select('products.*');

            case 'name':
            case 'active_ingredient':
            case 'unit_cost':
            case 'sale_price':
                return $query->orderBy($sortBy, $orderBy);

            case 'valid_stock':
                return $query->leftJoin('product_lots', function ($join) {
                    $join->on('products.id', '=', 'product_lots.product_id')
                        ->where('product_lots.expiration_date', '>=', now()->startOfDay())
                        ->where('product_lots.quantity', '>', 0);
                })
                    ->groupBy('products.id')
                    ->orderBy(DB::raw('COALESCE(SUM(product_lots.quantity), 0)'), $orderBy)
                    ->select('products.*');

            case 'next_expiration':
                return $query->leftJoin('product_lots', function ($join) {
                    $join->on('products.id', '=', 'product_lots.product_id')
                        ->where('product_lots.expiration_date', '>=', now()->startOfDay());
                })
                    ->groupBy('products.id')
                    ->orderBy(DB::raw('MIN(product_lots.expiration_date)'), $orderBy)
                    ->select('products.*');

            default:
                return $query->orderBy('products.id', 'asc');
        }
    }

    /**
     * Obtiene la consulta filtrada para conteos de productos.
     */
    public function getFilteredQuery(Request $request): Builder
    {
        $query = $this->getBaseQuery();
        $isHistoryView = $request->boolean('history');

        $filters = [
            'q' => $request->q,
            'laboratoryId' => $request->laboratoryId,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
            'is_history' => $isHistoryView,
        ];

        if ($isHistoryView) {
            $filters['status'] = ['approved', 'rejected'];
        } else {
            $filters['status'] = 'pending';
        }

        $query = $this->applyFiltersToCount($query, $filters);
        $query = $this->applySortingToCount($query, $request->input('sortBy'), $request->input('orderBy', 'desc'));

        return $query;
    }

    /**
     * Obtiene la consulta filtrada para productos (vista de inventario).
     */
    public function getProductsFilteredQuery(Request $request): Builder
    {
        $query = $this->getProductsBaseQuery();

        // --- INICIO DE LA LÓGICA MODIFICADA ---
        // 1. Buscamos el ID del ciclo de inventario que está actualmente activo.
        $activeCycleId = InventoryCycle::where('status', 'active')->value('id');

        // 2. Si se encontró un ciclo activo, filtramos los productos que ya han sido contados en este ciclo.
        if ($activeCycleId) {
            // `whereDoesntHave` excluye los productos que tienen un conteo (`productCounts`)
            // que coincide con el `cycle_id` activo.
            $query->whereDoesntHave('productCounts', function (Builder $subQuery) use ($activeCycleId) {
                $subQuery->where('cycle_id', $activeCycleId);
            });
        }
        // Si no hay ciclo activo, se muestran todos los productos (sujeto a otros filtros).
        // --- FIN DE LA LÓGICA MODIFICADA ---

        // Aplicamos el resto de los filtros de la solicitud
        $filters = [
            'q' => $request->q,
            'laboratoryId' => $request->laboratoryId,
            'originId' => $request->originId,
            'hasStock' => $request->hasStock,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
        ];

        $query = $this->applyFiltersToProducts($query, $filters);
        $query = $this->applySortingToProducts($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }

    /**
     * Obtiene estadísticas de conteos por usuario.
     */
    public function getCountStatisticsByUser(int $userId): array
    {
        $statistics = ProductCount::where('user_id', $userId)
            ->selectRaw('
                status,
                COUNT(*) as count,
                SUM(CASE WHEN discrepancy > 0 THEN 1 ELSE 0 END) as overages,
                SUM(CASE WHEN discrepancy < 0 THEN 1 ELSE 0 END) as shortages,
                SUM(CASE WHEN discrepancy = 0 THEN 1 ELSE 0 END) as exact_counts,
                AVG(ABS(discrepancy)) as avg_discrepancy
            ')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'pending' => $statistics->get('pending'),
            'approved' => $statistics->get('approved'),
            'rejected' => $statistics->get('rejected'),
        ];
    }

    /**
     * Obtiene conteos recientes para un producto específico.
     */
    public function getRecentCountsForProduct(int $productId, int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return ProductCount::with(['user'])
            ->where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Obtiene productos con discrepancias frecuentes.
     */
    public function getProductsWithFrequentDiscrepancies(int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return Product::withCount([
            'productCounts as discrepancy_count' => function ($query) {
                $query->where('discrepancy', '!=', 0)
                    ->where('created_at', '>=', now()->subDays(30));
            }
        ])
            ->with(['laboratory'])
            ->having('discrepancy_count', '>', 0)
            ->orderBy('discrepancy_count', 'desc')
            ->limit($limit)
            ->get();
    }
}
