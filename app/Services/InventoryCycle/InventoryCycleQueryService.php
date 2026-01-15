<?php

namespace App\Services\InventoryCycle;

use App\Models\InventoryCycle;
use App\Models\InvoiceCount;
use App\Models\Product;
use App\Models\ProductCount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryCycleQueryService
{
    private function getBaseQuery(): Builder
    {
        return ProductCount::query()->select('product_counts.*')->with([
            'product' => function ($query) {
                $query->with(['lots', 'laboratory']);
            },
            'user',
            'supervisor',
            'cycle',
        ]);
    }

    private function getProductsBaseQuery(): Builder
    {
        return Product::query()->with(['lots', 'laboratory', 'origin']);
    }

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

        if (!empty($filters['discrepancyFilter'])) {
            // Detectar el nombre de la tabla del modelo base
            $model = $query->getModel();
            $tableName = $model->getTable();
            $discrepancyColumn = "{$tableName}.discrepancy";
            
            switch ($filters['discrepancyFilter']) {
                case 'with_discrepancy':
                    $query->where($discrepancyColumn, '!=', 0);
                    break;
                case 'surplus':
                    $query->where($discrepancyColumn, '>', 0);
                    break;
                case 'shortage':
                    $query->where($discrepancyColumn, '<', 0);
                    break;
                case 'exact':
                    $query->where($discrepancyColumn, '=', 0);
                    break;
            }
        }

        if (!empty($filters['cycleId'])) {
            // Detectar el nombre de la tabla del modelo base
            $model = $query->getModel();
            $tableName = $model->getTable();
            $cycleIdColumn = "{$tableName}.cycle_id";
            $query->where($cycleIdColumn, $filters['cycleId']);
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

    private function applyFiltersToProducts(Builder $query, array $filters): Builder
    {
        /*if (!empty($filters['q'])) {
            $searchTerm = "%{$filters['q']}%";
            $query->where(function ($subQuery) use ($searchTerm) {
                $subQuery->where('name', 'like', $searchTerm)
                    ->orWhere('active_ingredient', 'like', $searchTerm)
                    ->orWhere('barcode', 'like', value: $searchTerm)
                    ->orWhere('id', 'like', $searchTerm);
            });
        }*/

        if (!empty($filters['q'])) {
            $searchTerm = "%{$filters['q']}%";
            $isStrictSearch = $filters['isStrictSearch'] ?? false;

            $query->where(function ($subQuery) use ($searchTerm, $isStrictSearch) {

                if ($isStrictSearch) {
                    $subQuery->where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('active_ingredient', 'like', "%{$searchTerm}%")
                        ->orWhere('barcode', 'like', $searchTerm)
                        ->orWhere('id', 'like', $searchTerm);
                } else {
                    $words = explode(' ', $searchTerm);
                    foreach ($words as $word) {
                        $subQuery->where(function ($wordQuery) use ($word) {
                            $wordQuery->where('name', 'like', "%{$word}%")
                                ->orWhere('active_ingredient', 'like', "%{$word}%")
                                ->orWhereHas('laboratory', function ($labQuery) use ($word) {
                                    $labQuery->where('name', 'like', "%{$word}%");
                                });
                        });
                    }
                }
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

    private function applySortingToCount(Builder $query, ?string $sortBy, string $orderBy): Builder
    {
        $tableName = $query->getModel()->getTable();

        $query->select("{$tableName}.*");

        switch ($sortBy) {
            case 'product.name':
                return $query->join('products', "{$tableName}.product_id", '=', 'products.id')
                    ->orderBy('products.name', $orderBy);

            case 'laboratory.name':
                return $query->join('products', "{$tableName}.product_id", '=', 'products.id')
                    ->join('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
                    ->orderBy('laboratories.name', $orderBy);

            case 'user.name':
                return $query->join('users', "{$tableName}.user_id", '=', 'users.id')
                    ->orderBy('users.name', $orderBy);

            case 'counted_quantity':
            case 'system_quantity':
            case 'discrepancy':
            case 'final_quantity':
            case 'created_at':
            case 'processed_at':
                return $query->orderBy("{$tableName}.{$sortBy}", $orderBy);

            default:
                $defaultSortColumn = ($tableName === 'product_counts' && $query->getQuery()->wheres && in_array('confirmed', array_column($query->getQuery()->wheres, 'values')))
                    ? 'processed_at'
                    : 'created_at';
                return $query->orderBy($defaultSortColumn, 'desc');
        }
    }

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

    public function getFilteredQuery(Request $request): Builder
    {
        $query = $this->getBaseQuery();
        $isHistoryView = $request->boolean('history');

        $filters = [
            'q' => $request->q,
            'laboratoryId' => $request->laboratoryId,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
            'cycleId' => $request->cycleId,
            'discrepancyFilter' => $request->discrepancyFilter,
            'is_history' => $isHistoryView,
        ];

        
        if ($isHistoryView || $request->cycleId) {
            $filters['status'] = ['approved', 'rejected', 'pending'];
        } else {
            $filters['status'] =  'pending';
        }
      
        $query = $this->applyFiltersToCount($query, $filters);
        $query = $this->applySortingToCount($query, $request->input('sortBy'), $request->input('orderBy', 'desc'));

        return $query;
    }

    public function getProductsFilteredQuery(Request $request): Builder
    {
        $query = $this->getProductsBaseQuery();

        $activeCycleId = InventoryCycle::where('status', 'active')->value('id');

        if ($activeCycleId) {
            $query->whereDoesntHave('productCounts', function (Builder $subQuery) use ($activeCycleId) {
                $subQuery->where('cycle_id', $activeCycleId);
            });
        }

        $filters = [
            'q' => $request->q,
            'laboratoryId' => $request->laboratoryId,
            'originId' => $request->originId,
            'hasStock' => $request->hasStock,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
            'isStrictSearch' => filter_var($request->get('isStrictSearch'), FILTER_VALIDATE_BOOLEAN)
        ];

        $query = $this->applyFiltersToProducts($query, $filters);
        $query = $this->applySortingToProducts($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }

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

    public function getRecentCountsForProduct(int $productId, int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return ProductCount::with(['user'])
            ->where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

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

    public function getInvoiceDetailsToCountQuery(Request $request): Builder
    {
        $query = Product::query()->with(['lots', 'laboratory', 'origin']);

        $query->whereHas('invoiceDetails.invoice', function ($subQuery) {
            $subQuery->where('status', 'ordered');
        });

        $activeCycleId = InventoryCycle::where('status', 'active')->value('id');
        if ($activeCycleId) {
            $query->whereDoesntHave('invoiceCounts', function (Builder $subQuery) use ($activeCycleId) {
                $subQuery->where('cycle_id', $activeCycleId);
            });
        }

        $filters = [
            'q' => $request->q,
            'laboratoryId' => $request->laboratoryId,
            'originId' => $request->originId,
            'isStrictSearch' => filter_var($request->get('isStrictSearch'), FILTER_VALIDATE_BOOLEAN)
        ];

        $query = $this->applyFiltersToProducts($query, $filters);
        $query = $this->applySortingToProducts($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }

    private function getInvoiceCountBaseQuery(): Builder
    {
        return InvoiceCount::query()->select('invoices_counts.*')->with([
            'product' => function ($query) {
                $query->with(['lots', 'laboratory']);
            },
            'user',
            'supervisor',
            'cycle',
        ]);
    }

    public function getInvoiceCountFilteredQuery(Request $request): Builder
    {
        $query = $this->getInvoiceCountBaseQuery();

        $filters = [
            'q' => $request->q,
            'laboratoryId' => $request->laboratoryId,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
            'discrepancyFilter' => $request->discrepancyFilter,
            'status' => 'pending',
        ];

        $query = $this->applyFiltersToCount($query, $filters);
        $query = $this->applySortingToCount($query, $request->input('sortBy'), $request->input('orderBy', 'desc'));

        return $query;
    }

    public function getCashCloseItemsQuery(Request $request)
    {
        $activeCycleId = InventoryCycle::where('status', 'active')->value('id');

        $productCounts = ProductCount::query()
            ->select([
                'id',
                'product_id',
                'user_id',
                'supervisor_id',
                'discrepancy',
                'status',
                'updated_at',
                DB::raw("'product_count' as source_type")
            ])
            ->where('status', '!=', 'pending')
            ->where('cycle_id', $activeCycleId);

        $invoiceCounts = InvoiceCount::query()
            ->select([
                'id',
                'product_id',
                'user_id',
                'supervisor_id',
                'discrepancy',
                'status',
                'updated_at',
                DB::raw("'invoice_count' as source_type")
            ])
            ->where('status', '!=', 'pending')
            ->where('cycle_id', $activeCycleId);

        $unionQuery = $productCounts->unionAll($invoiceCounts);

        $query = DB::query()->fromSub($unionQuery, 'discrepancies')
            ->leftJoin('products', 'discrepancies.product_id', '=', 'products.id')
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->leftJoin('users', 'discrepancies.user_id', '=', 'users.id')
            ->leftJoin('users as supervisors', 'discrepancies.supervisor_id', '=', 'supervisors.id')
            ->where('discrepancies.discrepancy', '!=', 0)
            ->select([
                'discrepancies.id',
                'discrepancies.discrepancy',
                'discrepancies.source_type',
                'discrepancies.updated_at as processed_date',
                'products.name as product_name',
                'products.sale_price as product_sale_price',
                'products.unit_cost as product_unit_cost',
                'laboratories.name as laboratory_name',
                'users.username as user_name',
                'users.email as user_email',
                'supervisors.username as supervisor_name',
                'supervisors.email as supervisor_email'
            ]);

        if ($request->filled('searchQuery')) {
            $searchTerm = '%' . $request->input('searchQuery') . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('products.name', 'like', $searchTerm)
                    ->orWhere('users.username', 'like', $searchTerm)
                    ->orWhere('users.email', 'like', $searchTerm);
            });
        }

        if ($request->filled('startDate')) {
            $query->where('discrepancies.updated_at', '>=', $request->input('startDate'));
        }

        if ($request->filled('endDate')) {
            $query->where('discrepancies.updated_at', '<=', $request->input('endDate') . ' 23:59:59');
        }

        $sortBy = $request->input('sortBy', 'processed_date');
        $orderBy = $request->input('orderBy', 'desc');

        $sortableColumns = [
            'product.name' => 'products.name',
            'discrepancy' => 'discrepancies.discrepancy',
            'user.name' => 'users.username',
            'amount' => DB::raw('products.sale_price * discrepancies.discrepancy'),
            'processed_date' => 'discrepancies.updated_at'
        ];

        if (isset($sortableColumns[$sortBy])) {
            $query->orderBy($sortableColumns[$sortBy], $orderBy);
        } else {
            $query->orderBy('discrepancies.updated_at', 'desc');
        }

        return $query;
    }
    public function getCycleSummaryQuery(Request $request)
    {
        $query = DB::table('product_counts as pc')
            ->join('inventory_cycles as ic', 'pc.cycle_id', '=', 'ic.id')
            ->leftJoin('products as p', 'pc.product_id', '=', 'p.id')
            ->select([
                'pc.cycle_id',
                'ic.start_date',
                'ic.end_date',
                'ic.status as cycle_status',
                DB::raw('COUNT(pc.id) as total_products'),
                DB::raw('SUM(CASE 
                WHEN pc.discrepancy > 0 AND p.sale_price IS NOT NULL 
                THEN pc.discrepancy * p.sale_price 
                ELSE 0 
            END) as total_surplus'),
                DB::raw('SUM(CASE 
                WHEN pc.discrepancy < 0 AND p.sale_price IS NOT NULL 
                THEN ABS(pc.discrepancy) * p.sale_price 
                ELSE 0 
            END) as total_shortage'),
                DB::raw('SUM(CASE 
                WHEN pc.discrepancy IS NOT NULL AND p.sale_price IS NOT NULL 
                THEN pc.discrepancy * p.sale_price 
                ELSE 0 
            END) as net_total')
            ])
            ->whereNotNull('pc.cycle_id')
            ->groupBy('pc.cycle_id', 'ic.start_date', 'ic.end_date', 'ic.status');

        $filters = [
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
            'cycleStatus' => $request->cycleStatus,
        ];

        $query = $this->applyCycleSummaryFilters($query, $filters);
        $query = $this->applyCycleSummarySorting($query, $request->input('sortBy'), $request->input('orderBy', 'desc'));

        return $query;
    }

    private function applyCycleSummaryFilters($query, array $filters)
    {
        if (!empty($filters['startDate'])) {
            $query->where('ic.start_date', '>=', $filters['startDate']);
        }

        if (!empty($filters['endDate'])) {
            $query->where('ic.end_date', '<=', $filters['endDate']);
        }

        if (!empty($filters['cycleStatus'])) {
            $query->where('ic.status', $filters['cycleStatus']);
        }

        return $query;
    }

    private function applyCycleSummarySorting($query, ?string $sortBy, string $orderBy = 'desc')
    {
        $validSortFields = [
            'cycle_id' => 'pc.cycle_id',
            'start_date' => 'ic.start_date',
            'end_date' => 'ic.end_date',
            'cycle_status' => 'ic.status',
            'total_products' => 'total_products',
            'total_surplus' => 'total_surplus',
            'total_shortage' => 'total_shortage',
            'net_total' => 'net_total'
        ];

        if ($sortBy && isset($validSortFields[$sortBy])) {
            $query->orderBy($validSortFields[$sortBy], $orderBy);
        } else {
            $query->orderBy('ic.start_date', 'desc');
        }

        return $query;
    }


      public function getSalesDetailsToCountQuery(Request $request): Builder
    {
        $query = Product::query()->with(['lots', 'laboratory', 'origin']);

        $query->whereHas('orderDetails.order.cashClosing.dailyClosure', function ($subQuery) {
            $subQuery->where('status', 'completed'); 
        });

        $activeCycleId = InventoryCycle::where('status', 'active')->value('id');
        if ($activeCycleId) {
            $query->whereDoesntHave('salesCounts', function (Builder $subQuery) use ($activeCycleId) {
                $subQuery->where('cycle_id', $activeCycleId);
            });
        }

        $filters = [
            'q' => $request->q,
            'laboratoryId' => $request->laboratoryId,
            'originId' => $request->originId,
            'isStrictSearch' => filter_var($request->get('isStrictSearch'), FILTER_VALIDATE_BOOLEAN)
        ];

        $query = $this->applyFiltersToProducts($query, $filters);
        $query = $this->applySortingToProducts($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }
}
