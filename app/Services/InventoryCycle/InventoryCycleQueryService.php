<?php

declare(strict_types=1);

namespace App\Services\InventoryCycle;

use App\Models\InventoryCycle;
use App\Models\InvoiceCount;
use App\Models\Product;
use App\Models\ProductCount;
use App\Models\SaleCount;
use Carbon\Carbon;
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
            'user.employee',
            'supervisor.employee',
            'cycle',
        ]);
    }

    private function getProductsBaseQuery(): Builder
    {
        return Product::query()
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->selectRaw("products.*, COALESCE((SELECT SUM(quantity) FROM product_lots WHERE product_lots.product_id = products.id), 0) AS stock_calculado")
            ->with(['lots', 'laboratory', 'origin']);
    }

    /**
     * Construye un query con la unión de product_counts, invoice_counts y sale_counts
     * filtrado por ciclo y estado según sea necesario.
     */
    private function buildDiscrepanciesUnionQuery(int|string|null $cycleId = null, bool $includePending = false, bool $includeTraceability = false)
    {
        $cycleId = $cycleId !== null ? (int) $cycleId : null;

        $traceabilitySelect = $includeTraceability
            ? "EXISTS(
                SELECT 1 FROM product_distributions WHERE product_count_id = pc.id
                UNION
                SELECT 1 FROM inventory_movements WHERE product_id = pc.product_id 
                AND movement_type IN ('adjustment', 'loss')
                AND ABS(TIMESTAMPDIFF(SECOND, created_at, pc.updated_at)) < 30
            ) as has_traceability"
            : "0 as has_traceability";

        $productCounts = ProductCount::query()
            ->from('product_counts as pc')
            ->select([
                'pc.id',
                'pc.cycle_id',
                'pc.product_id',
                'pc.user_id',
                'pc.supervisor_id',
                'pc.counted_quantity',
                'pc.system_quantity',
                'pc.discrepancy',
                'pc.status',
                'pc.created_at',
                'pc.updated_at',
                DB::raw("'product_count' as source_type"),
                DB::raw($traceabilitySelect)
            ])
            ->when(!$includePending, fn ($q) => $q->where('pc.status', 'approved'))
            ->when($cycleId, fn ($q) => $q->where('pc.cycle_id', $cycleId));

        $invoiceTraceabilitySelect = $includeTraceability
            ? "EXISTS(
                SELECT 1 FROM invoice_count_distributions WHERE invoice_count_id = ic.id
                UNION
                SELECT 1 FROM inventory_movements WHERE product_id = ic.product_id 
                AND movement_type IN ('adjustment', 'loss')
                AND ABS(TIMESTAMPDIFF(SECOND, created_at, ic.updated_at)) < 30
            ) as has_traceability"
            : "0 as has_traceability";

        $invoiceCounts = InvoiceCount::query()
            ->from('invoices_counts as ic')
            ->select([
                'ic.id',
                'ic.cycle_id',
                'ic.product_id',
                'ic.user_id',
                'ic.supervisor_id',
                'ic.counted_quantity',
                'ic.system_quantity',
                'ic.discrepancy',
                'ic.status',
                'ic.created_at',
                'ic.updated_at',
                DB::raw("'invoice_count' as source_type"),
                DB::raw($invoiceTraceabilitySelect)
            ])
            ->when(!$includePending, fn ($q) => $q->where('ic.status', 'approved'))
            ->when($cycleId, fn ($q) => $q->where('ic.cycle_id', $cycleId));

        $saleTraceabilitySelect = $includeTraceability
            ? "EXISTS(
                SELECT 1 FROM sale_count_distributions WHERE sale_count_id = sc.id
                UNION
                SELECT 1 FROM inventory_movements WHERE product_id = sc.product_id 
                AND movement_type IN ('adjustment', 'loss')
                AND ABS(TIMESTAMPDIFF(SECOND, created_at, sc.updated_at)) < 30
            ) as has_traceability"
            : "0 as has_traceability";

        $saleCounts = SaleCount::query()
            ->from('sales_counts as sc')
            ->select([
                'sc.id',
                'sc.cycle_id',
                'sc.product_id',
                'sc.user_id',
                'sc.supervisor_id',
                'sc.counted_quantity',
                'sc.system_quantity',
                'sc.discrepancy',
                'sc.status',
                'sc.created_at',
                'sc.updated_at',
                DB::raw("'sale_count' as source_type"),
                DB::raw($saleTraceabilitySelect)
            ])
            ->when(!$includePending, fn ($q) => $q->where('sc.status', 'approved'))
            ->when($cycleId, fn ($q) => $q->where('sc.cycle_id', $cycleId));

        return $productCounts->unionAll($invoiceCounts)->unionAll($saleCounts);
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

        if (!empty($filters['userId'])) {
            // Detectar el nombre de la tabla del modelo base
            $model = $query->getModel();
            $tableName = $model->getTable();
            $userIdColumn = "{$tableName}.user_id";
            $query->where($userIdColumn, $filters['userId']);
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
                    ->orderBy('users.username', $orderBy);

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
                return $query->orderBy($defaultSortColumn, 'asc');
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

            case 'latest_sale_date':
                return $query->orderBy('latest_sale_date', $orderBy);

            default:
                // Si existe la columna virtual latest_sale_date, ordenar por ella por defecto
                if (str_contains($query->toSql(), 'latest_sale_date')) {
                    return $query->orderBy('latest_sale_date', $orderBy);
                }
                return $query->orderBy('products.id', $orderBy);
        }
    }

    private function applyNotAuthUserFilterToCount(Builder $query): Builder
    {
        // El admin (role_id = 1) puede ver todos los conteos, incluyendo los propios
        $user = auth()->user();
        if (!$user || (int) $user->role_id !== 1) {
            $query->where('user_id', '!=', auth()->id());
        }

        return $query;
    }

    public function getFilteredQuery(Request $request): Builder
    {
        $query = $this->getBaseQuery()
            ->whereHas('product', function (Builder $q) {
                $q->where(function ($q2) {
                    $q2->whereNull('is_deleted')->orWhere('is_deleted', 0);
                });
            });
        $isHistoryView = $request->boolean('history');

        $filters = [
            'q' => $request->q,
            'laboratoryId' => $request->laboratoryId,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
            'cycleId' => $request->cycleId,
            'discrepancyFilter' => $request->discrepancyFilter,
            'userId' => $request->userId,
            'is_history' => $isHistoryView,
        ];


        if ($isHistoryView || $request->cycleId) {
            $filters['status'] = ['approved', 'rejected', 'pending'];
        } elseif ($request->cycleId) {
            // Para detalles de ciclo, no filtrar por status
            $filters['status'] = null;
        } else {
            $filters['status'] = 'pending';
            // Excluir conteos sin discrepancia: no tienen relevancia para verificación
            $query->where('discrepancy', '!=', 0);
        }

        $query = $this->applyFiltersToCount($query, $filters);
        $query = $this->applyNotAuthUserFilterToCount($query);
        $query = $this->applySortingToCount($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

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
        return Product::query()
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->withCount([
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

    public function getSalesDetailsToCountQuery(Request $request): Builder
    {
        $query = $this->getProductsBaseQuery();

        $activeCycle = InventoryCycle::where('status', 'active')->first();

        // Si no hay ciclo activo, retornar query vacía — no hay punto de referencia válido
        if (!$activeCycle) {
            return $query->whereRaw('1 = 0');
        }

        $activeCycleId  = $activeCycle->id;
        $afterCycleStart = Carbon::parse($activeCycle->start_date)->addSecond();

        $query->addSelect([
            'latest_sale_date' => DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->whereColumn('order_details.product_id', 'products.id')
                ->where('orders.status', 'completed')
                ->where('orders.order_date', '>', $afterCycleStart)
                ->selectRaw('MAX(orders.order_date)')
        ]);

        $query->whereExists(function ($sub) use ($activeCycleId, $afterCycleStart) {
            $sub->select(DB::raw(1))
                ->from('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->whereColumn('order_details.product_id', 'products.id')
                ->where('orders.status', 'completed')
                ->where('orders.order_date', '>', $afterCycleStart);

            // Solo mostrar si no hay un conteo posterior a esta venta en el ciclo actual
            $sub->whereNotExists(function ($countSub) use ($activeCycleId) {
                $countSub->select(DB::raw(1))
                    ->from('sales_counts')
                    ->whereColumn('sales_counts.product_id', 'products.id')
                    ->where('sales_counts.cycle_id', $activeCycleId)
                    ->whereRaw('sales_counts.created_at >= orders.order_date');
            });
        });

        $filters = [
            'q'            => $request->q,
            'laboratoryId' => $request->laboratoryId,
            'originId'     => $request->originId,
            'isStrictSearch' => filter_var($request->get('isStrictSearch'), FILTER_VALIDATE_BOOLEAN)
        ];

        $query = $this->applyFiltersToProducts($query, $filters);
        $query = $this->applySortingToProducts($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }

    public function getInvoiceDetailsToCountQuery(Request $request): Builder
    {
        $query = $this->getProductsBaseQuery();

        $activeCycle = InventoryCycle::where('status', 'active')->first();

        // Si no hay ciclo activo, retornar query vacía — no hay punto de referencia válido
        if (!$activeCycle) {
            return $query->whereRaw('1 = 0');
        }

        $activeCycleId   = $activeCycle->id;
        $afterCycleStart = Carbon::parse($activeCycle->start_date)->addSecond();

        $query->addSelect([
            'latest_invoice_date' => DB::table('invoice_details')
                ->join('invoices', 'invoice_details.invoice_id', '=', 'invoices.id')
                ->whereColumn('invoice_details.product_id', 'products.id')
                ->where('invoices.status', 'ordered')
                ->where('invoices.updated_at', '>', $afterCycleStart)
                ->selectRaw('MAX(invoices.updated_at)')
        ]);

        $query->whereExists(function ($sub) use ($activeCycleId, $afterCycleStart) {
            $sub->select(DB::raw(1))
                ->from('invoice_details')
                ->join('invoices', 'invoice_details.invoice_id', '=', 'invoices.id')
                ->whereColumn('invoice_details.product_id', 'products.id')
                ->where('invoices.status', 'ordered')
                ->where('invoices.updated_at', '>', $afterCycleStart);

            // Solo mostrar si no hay un conteo posterior a la fecha en que se ordenó la factura en el ciclo actual
            $sub->whereNotExists(function ($countSub) use ($activeCycleId) {
                $countSub->select(DB::raw(1))
                    ->from('invoices_counts')
                    ->whereColumn('invoices_counts.product_id', 'products.id')
                    ->where('invoices_counts.cycle_id', $activeCycleId)
                    ->whereRaw('invoices_counts.created_at >= invoices.updated_at');
            });
        });

        $filters = [
            'q'             => $request->q,
            'laboratoryId'  => $request->laboratoryId,
            'originId'      => $request->originId,
            'isStrictSearch'=> filter_var($request->get('isStrictSearch'), FILTER_VALIDATE_BOOLEAN),
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
            'user.employee',
            'supervisor.employee',
            'cycle',
        ]);
    }

    public function getInvoiceCountFilteredQuery(Request $request): Builder
    {
        $query = $this->getInvoiceCountBaseQuery()
            ->whereHas('product', function (Builder $q) {
                $q->where(function ($q2) {
                    $q2->whereNull('is_deleted')->orWhere('is_deleted', 0);
                });
            });

        // Filtrar solo productos que estén en facturas con fecha >= 2026-01-25
        $query->whereHas('product.invoiceDetails.invoice', function ($subQuery) {
            $subQuery->where('created_invoice_date', '>=', '2026-01-25');
        });

        // Solo conteos con fecha superior (al menos 1 segundo) a la fecha de apertura del ciclo
        $query->whereExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('inventory_cycles')
                ->whereColumn('inventory_cycles.id', 'invoices_counts.cycle_id')
                ->whereRaw('invoices_counts.created_at > DATE_ADD(inventory_cycles.start_date, INTERVAL 1 SECOND)');
        });

        $filters = [
            'q' => $request->q,
            'laboratoryId' => $request->laboratoryId,
            'startDate' => $request->startDate,
            'endDate' => $request->endDate,
            'discrepancyFilter' => $request->discrepancyFilter,
            'userId' => $request->userId,
            'status' => 'pending',
        ];

        $query = $this->applyFiltersToCount($query, $filters);
        $query = $this->applyNotAuthUserFilterToCount($query);
        $query = $this->applySortingToCount($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }

    public function getCashCloseItemsQuery(Request $request)
    {
        $activeCycleId = InventoryCycle::where('status', 'active')->value('id');

        $unionQuery = $this->buildDiscrepanciesUnionQuery($activeCycleId, false, true);

        $query = DB::query()->fromSub($unionQuery, 'discrepancies')
            ->leftJoin('products', 'discrepancies.product_id', '=', 'products.id')
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->leftJoin('users', 'discrepancies.user_id', '=', 'users.id')
            ->leftJoin('employees as user_employees', 'users.id', '=', 'user_employees.user_id')
            ->leftJoin('users as supervisors', 'discrepancies.supervisor_id', '=', 'supervisors.id')
            ->leftJoin('employees as supervisor_employees', 'supervisors.id', '=', 'supervisor_employees.user_id')
            ->where('discrepancies.discrepancy', '!=', 0)
            ->where(function ($q) {
                $q->whereNull('products.is_deleted')->orWhere('products.is_deleted', 0);
            })
            ->select([
                'discrepancies.id',
                'discrepancies.product_id',
                'discrepancies.discrepancy',
                'discrepancies.status',
                'discrepancies.source_type',
                'discrepancies.has_traceability',
                'discrepancies.updated_at as processed_date',
                'products.name as product_name',
                'products.active_ingredient as active_ingredient',
                'products.sale_price as product_sale_price',
                'products.unit_cost as product_unit_cost',
                'laboratories.name as laboratory_name',
                'users.username as user_name',
                'users.email as user_email',
                'user_employees.name as user_employee_name',
                'user_employees.last_name as user_employee_last_name',
                'supervisors.username as supervisor_name',
                'supervisors.email as supervisor_email',
                'supervisor_employees.name as supervisor_employee_name',
                'supervisor_employees.last_name as supervisor_employee_last_name'
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

        // Manejar ordenamiento especial para columnas que requieren múltiples campos
        if ($sortBy === 'user.name') {
            $query->orderBy(DB::raw('COALESCE(user_employees.name, users.username)'), $orderBy);
            $query->orderBy(DB::raw('COALESCE(user_employees.last_name, \'\')'), $orderBy);
        } elseif ($sortBy === 'supervisor.name') {
            $query->orderBy(DB::raw('COALESCE(supervisor_employees.name, supervisors.username)'), $orderBy);
            $query->orderBy(DB::raw('COALESCE(supervisor_employees.last_name, \'\')'), $orderBy);
        } else {
            $sortableColumns = [
                'product.name' => 'products.name',
                'product.laboratory.name' => 'laboratories.name',
                'discrepancy' => 'discrepancies.discrepancy',
                'product.unit_cost' => 'products.unit_cost',
                'amount' => DB::raw('products.sale_price * discrepancies.discrepancy'),
                'processed_date' => 'discrepancies.updated_at'
            ];

            if (isset($sortableColumns[$sortBy])) {
                $query->orderBy($sortableColumns[$sortBy], $orderBy);
            } else {
                $query->orderBy('discrepancies.updated_at', 'desc');
            }
        }

        return $query;
    }
    public function getCycleSummaryQuery(Request $request)
    {
        $unionQuery = $this->buildDiscrepanciesUnionQuery();

        $query = DB::query()->fromSub($unionQuery, 'discrepancies')
            ->join('inventory_cycles as ic', 'discrepancies.cycle_id', '=', 'ic.id')
            ->leftJoin('products as p', 'discrepancies.product_id', '=', 'p.id')
            ->select([
                'discrepancies.cycle_id',
                'ic.start_date',
                'ic.end_date',
                'ic.status as cycle_status',
                DB::raw('COUNT(discrepancies.id) as total_products'),
                DB::raw('SUM(CASE 
                    WHEN discrepancies.discrepancy > 0 AND p.sale_price IS NOT NULL 
                    THEN discrepancies.discrepancy * p.sale_price 
                    ELSE 0 
                END) as total_surplus'),
                DB::raw('SUM(CASE 
                    WHEN discrepancies.discrepancy < 0 AND p.sale_price IS NOT NULL 
                    THEN ABS(discrepancies.discrepancy) * p.sale_price 
                    ELSE 0 
                END) as total_shortage'),
                DB::raw('SUM(CASE 
                    WHEN discrepancies.discrepancy IS NOT NULL AND p.sale_price IS NOT NULL 
                    THEN discrepancies.discrepancy * p.sale_price 
                    ELSE 0 
                END) as net_total'),
            ])
            ->whereNotNull('discrepancies.cycle_id')
            ->groupBy('discrepancies.cycle_id', 'ic.start_date', 'ic.end_date', 'ic.status');

        $filters = [
            'startDate'   => $request->startDate,
            'endDate'     => $request->endDate,
            'cycleStatus' => $request->cycleStatus,
        ];

        $query = $this->applyCycleSummaryFilters($query, $filters);
        $query = $this->applyCycleSummarySorting($query, $request->input('sortBy'), $request->input('orderBy', 'desc'));

        return $query;
    }

    public function getCycleDetailedCountsQuery(Request $request)
    {
        // Castear a int: el parametro llega como string desde el query string (?cycleId=2)
        $cycleId = (int) $request->input('cycleId');
        if (!$cycleId) {
            return DB::query()->whereRaw('1 = 0');
        }

        $lotsAggregate = DB::table('product_lots')
            ->where('quantity', '>', 0)
            ->groupBy('product_id')
            ->select([
                'product_id',
                DB::raw("GROUP_CONCAT(DISTINCT NULLIF(TRIM(location), '') ORDER BY location SEPARATOR ', ') as lot_locations_str"),
            ]);

        $query = DB::query()->fromSub($unionQuery, 'counts')
            ->leftJoin('products', 'counts.product_id', '=', 'products.id')
            ->leftJoin('laboratories', 'products.laboratory_id', '=', 'laboratories.id')
            ->leftJoinSub($lotsAggregate, 'lots_agg', 'products.id', '=', 'lots_agg.product_id')
            ->leftJoin('users', 'counts.user_id', '=', 'users.id')
            ->leftJoin('employees as user_employees', 'users.id', '=', 'user_employees.user_id')
            ->leftJoin('users as supervisors', 'counts.supervisor_id', '=', 'supervisors.id')
            ->leftJoin('employees as supervisor_employees', 'supervisors.id', '=', 'supervisor_employees.user_id')
            ->select([
                'counts.id',
                'counts.cycle_id',
                'counts.product_id',
                'counts.user_id',
                'counts.supervisor_id',
                'counts.counted_quantity',
                'counts.system_quantity',
                'counts.discrepancy',
                'counts.status',
                'counts.source_type',
                'counts.created_at',
                'counts.updated_at',
                'products.name as product_name',
                'products.photo_url as product_photo_url',
                'products.iva as product_iva',
                'products.psychotropic as product_psychotropic',
                'products.unit_cost as product_unit_cost',
                'products.sale_price as product_sale_price',
                'products.is_colombian_origin as product_is_colombian_origin',
                'products.active_ingredient as product_active_ingredient',
                DB::raw("COALESCE(lots_agg.lot_locations_str, 'N/A') as product_location"),
                'laboratories.name as laboratory_name',
                'users.email as user_email',
                'users.username as user_username',
                'user_employees.name as user_employee_name',
                'user_employees.last_name as user_employee_last_name',
                'supervisors.email as supervisor_email',
                'supervisors.username as supervisor_username',
                'supervisor_employees.name as supervisor_employee_name',
                'supervisor_employees.last_name as supervisor_employee_last_name',
            ])
            ->where('counts.cycle_id', $cycleId)
            ->where(function ($q) {
                $q->whereNull('products.is_deleted')->orWhere('products.is_deleted', 0);
            });

        // Búsqueda general
        if ($request->filled('q')) {
            $searchTerm = '%' . $request->input('q') . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('products.name', 'like', $searchTerm)
                    ->orWhere('users.email', 'like', $searchTerm)
                    ->orWhere('users.username', 'like', $searchTerm)
                    ->orWhere('user_employees.name', 'like', $searchTerm)
                    ->orWhere('user_employees.last_name', 'like', $searchTerm);
            });
        }

        if ($request->filled('laboratoryId')) {
            $query->where('products.laboratory_id', $request->input('laboratoryId'));
        }

        if ($request->filled('discrepancyFilter')) {
            switch ($request->input('discrepancyFilter')) {
                case 'with_discrepancy':
                    $query->where('counts.discrepancy', '!=', 0);
                    break;
                case 'surplus':
                    $query->where('counts.discrepancy', '>', 0);
                    break;
                case 'shortage':
                    $query->where('counts.discrepancy', '<', 0);
                    break;
                case 'exact':
                    $query->where('counts.discrepancy', '=', 0);
                    break;
            }
        }

        if ($request->filled('userId')) {
            $query->where('counts.user_id', $request->input('userId'));
        }

        if ($request->filled('supervisorId')) {
            $query->where('counts.supervisor_id', $request->input('supervisorId'));
        }

        if ($request->filled('startDate')) {
            $query->where('counts.updated_at', '>=', $request->input('startDate'));
        }

        if ($request->filled('endDate')) {
            $query->where('counts.updated_at', '<=', $request->input('endDate') . ' 23:59:59');
        }

        $sortBy = $request->input('sortBy');
        $orderBy = $request->input('orderBy', 'desc');

        if ($sortBy) {
            switch ($sortBy) {
                case 'product.name':
                    $query->orderBy('products.name', $orderBy);
                    break;
                case 'laboratory.name':
                    $query->orderBy('laboratories.name', $orderBy);
                    break;
                case 'system_quantity':
                    $query->orderBy('counts.system_quantity', $orderBy);
                    break;
                case 'final_quantity':
                case 'counted_quantity':
                    $query->orderBy('counts.counted_quantity', $orderBy);
                    break;
                case 'discrepancy':
                    $query->orderBy('counts.discrepancy', $orderBy);
                    break;
                case 'user.email':
                    $query->orderBy('users.email', $orderBy);
                    break;
                case 'supervisor.email':
                    $query->orderBy('supervisors.email', $orderBy);
                    break;
                case 'processed_at':
                case 'updated_at':
                    $query->orderBy('counts.updated_at', $orderBy);
                    break;
                case 'created_at':
                    $query->orderBy('counts.created_at', $orderBy);
                    break;
                default:
                    $query->orderBy('counts.updated_at', 'desc');
                    break;
            }
        } else {
            $query->orderBy('products.name', 'asc');
        }

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
            'cycle_id' => 'discrepancies.cycle_id',
            'start_date' => 'ic.start_date',
            'end_date' => 'ic.end_date',
            'cycle_status' => 'ic.status',
            'total_products' => 'total_products',
            'total_surplus' => 'total_surplus',
            'total_shortage' => 'total_shortage',
            'net_total' => 'net_total',
        ];

        if ($sortBy && isset($validSortFields[$sortBy])) {
            $query->orderBy($validSortFields[$sortBy], $orderBy);
        } else {
            $query->orderBy('ic.start_date', 'desc');
        }

        return $query;
    }


    private function getSaleCountBaseQuery(): Builder
    {
        return SaleCount::query()->select('sales_counts.*')->with([
            'product' => function ($query) {
                $query->with(['lots', 'laboratory']);
            },
            'user.employee',
            'supervisor.employee',
            'cycle',
        ]);
    }

    public function getSaleCountFilteredQuery(Request $request): Builder
    {
        $query = $this->getSaleCountBaseQuery()
            ->whereHas('product', function (Builder $q) {
                $q->where(function ($q2) {
                    $q2->whereNull('is_deleted')->orWhere('is_deleted', 0);
                });
            });

        // Solo conteos con fecha superior (al menos 1 segundo) a la fecha de apertura del ciclo activo
        $query->whereExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('inventory_cycles')
                ->whereColumn('inventory_cycles.id', 'sales_counts.cycle_id')
                ->where('inventory_cycles.status', 'active')
                ->whereRaw('sales_counts.created_at > DATE_ADD(inventory_cycles.start_date, INTERVAL 1 SECOND)');
        });

        $filters = [
            'q'                => $request->q,
            'laboratoryId'     => $request->laboratoryId,
            'startDate'        => $request->startDate,
            'endDate'          => $request->endDate,
            'discrepancyFilter'=> $request->discrepancyFilter,
            'userId'           => $request->userId,
            'status'           => 'pending',
        ];

        $query = $this->applyFiltersToCount($query, $filters);
        $query = $this->applyNotAuthUserFilterToCount($query);
        $query = $this->applySortingToCount($query, $request->input('sortBy'), $request->input('orderBy', 'asc'));

        return $query;
    }

    public function getCycleFinancialTotals($cycleId)
    {
        $unionQuery = $this->buildDiscrepanciesUnionQuery($cycleId, true);

        return DB::query()->fromSub($unionQuery, 'counts')
            ->leftJoin('products as p', 'counts.product_id', '=', 'p.id')
            ->select([
                DB::raw('SUM(CASE 
                    WHEN counts.discrepancy > 0 
                    THEN counts.discrepancy * p.unit_cost 
                    ELSE 0 
                END) as total_surplus'),
                DB::raw('SUM(CASE 
                    WHEN counts.discrepancy < 0 
                    THEN ABS(counts.discrepancy) * p.unit_cost 
                    ELSE 0 
                END) as total_shortage'),
                DB::raw('SUM(counts.discrepancy * p.unit_cost) as net_total'),
            ])
            ->where('counts.cycle_id', $cycleId)
            ->first();
    }

    public function getDailyQuotasMatrixData(int $month, int $year, string $type = 'products'): array
    {
        $settings = \App\Models\GeneralSetting::first();
        $dailyQuota = (int) ($settings?->cyclic_inventory_daily_quota ?? 50);

        // Obtener todos los empleados con usuario vinculado
        $employees = \App\Models\Employee::where('is_active', true)
            ->whereNotNull('user_id')
            ->select(['id', 'name', 'last_name', 'user_id', 'photo'])
            ->orderBy('name', 'asc')
            ->get();

        $userIds = $employees->pluck('user_id')->filter()->unique()->toArray();

        $matrixMap = [];

        if ($type === 'totals') {
            // Obtener conteos de productos regulares, facturas y ventas
            $pCounts = ProductCount::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->selectRaw('DATE(created_at) as count_date, user_id, COUNT(*) as total_counts')
                ->groupBy('count_date', 'user_id')
                ->get();

            $iCounts = InvoiceCount::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->selectRaw('DATE(created_at) as count_date, user_id, COUNT(*) as total_counts')
                ->groupBy('count_date', 'user_id')
                ->get();

            $sCounts = SaleCount::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->selectRaw('DATE(created_at) as count_date, user_id, COUNT(*) as total_counts')
                ->groupBy('count_date', 'user_id')
                ->get();

            foreach ($pCounts as $item) {
                $uid = (int) $item->user_id;
                $matrixMap[$item->count_date][$uid] = ($matrixMap[$item->count_date][$uid] ?? 0) + (int) $item->total_counts;
            }
            foreach ($iCounts as $item) {
                $uid = (int) $item->user_id;
                $matrixMap[$item->count_date][$uid] = ($matrixMap[$item->count_date][$uid] ?? 0) + (int) $item->total_counts;
            }
            foreach ($sCounts as $item) {
                $uid = (int) $item->user_id;
                $matrixMap[$item->count_date][$uid] = ($matrixMap[$item->count_date][$uid] ?? 0) + (int) $item->total_counts;
            }
        } else {
            $countsQuery = match ($type) {
                'invoices' => InvoiceCount::query(),
                'sales'    => SaleCount::query(),
                'pending'  => ProductCount::where('status', 'pending'),
                default    => ProductCount::query(),
            };

            $counts = $countsQuery
                ->whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->selectRaw('DATE(created_at) as count_date, user_id, COUNT(*) as total_counts')
                ->groupBy('count_date', 'user_id')
                ->get();

            foreach ($counts as $item) {
                $uid = (int) $item->user_id;
                $matrixMap[$item->count_date][$uid] = (int) $item->total_counts;
            }
        }

        $startDate = Carbon::create($year, $month, 1);
        $daysInMonth = $startDate->daysInMonth;
        $today = now()->toDateString();

        $rows = [];
        for ($d = $daysInMonth; $d >= 1; $d--) {
            $currentDate = Carbon::create($year, $month, $d)->toDateString();
            if ($currentDate > $today) {
                continue;
            }

            $userCells = [];
            $dayTotal = 0;

            foreach ($employees as $emp) {
                $uId = (int) $emp->user_id;
                $countVal = $matrixMap[$currentDate][$uId] ?? 0;
                $dayTotal += $countVal;

                $userCells[$uId] = [
                    'count'     => $countVal,
                    'quota'     => $dailyQuota,
                    'fulfilled' => ($type === 'products') ? ($countVal >= $dailyQuota) : ($countVal > 0),
                ];
            }

            // Si hay conteos del día que no corresponden a empleados de la lista, sumarlos al total del día
            if (!empty($matrixMap[$currentDate])) {
                $dayTotal = array_sum($matrixMap[$currentDate]);
            }

            $rows[] = [
                'date'          => $currentDate,
                'formatted_date'=> Carbon::parse($currentDate)->format('d/m/Y'),
                'day_total'     => $dayTotal,
                'users'         => $userCells,
            ];
        }

        // Calcular resumen mensual
        $totalMonthCounts = 0;
        $activeDaysCount = 0;
        $employeeTotals = [];

        foreach ($rows as $row) {
            $totalMonthCounts += (int) $row['day_total'];
            if ($row['day_total'] > 0) {
                $activeDaysCount++;
            }
            foreach ($row['users'] as $uId => $userData) {
                $employeeTotals[$uId] = ($employeeTotals[$uId] ?? 0) + (int) $userData['count'];
            }
        }

        $dailyAverage = $activeDaysCount > 0 ? round($totalMonthCounts / $activeDaysCount, 1) : 0.0;
        $topEmployee = null;
        if (!empty($employeeTotals)) {
            arsort($employeeTotals);
            $topUserId = array_key_first($employeeTotals);
            $topEmpModel = $employees->firstWhere('user_id', $topUserId);
            if ($topEmpModel) {
                $topEmployee = [
                    'id' => $topEmpModel->id,
                    'user_id' => $topEmpModel->user_id,
                    'name' => trim("{$topEmpModel->name} {$topEmpModel->last_name}"),
                    'total_counts' => $employeeTotals[$topUserId],
                ];
            }
        }

        return [
            'month'       => $month,
            'year'        => $year,
            'daily_quota' => $dailyQuota,
            'employees'   => $employees,
            'data'        => $rows,
            'summary'     => [
                'total_month_counts' => $totalMonthCounts,
                'active_days'        => $activeDaysCount,
                'daily_average'      => $dailyAverage,
                'top_employee'       => $topEmployee,
            ],
        ];
    }
}
