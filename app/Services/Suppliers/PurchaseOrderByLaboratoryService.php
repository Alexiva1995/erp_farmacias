<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\PurchaseOrderByLaboratoryServiceInterface;
use App\Models\AutoOrderDetail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseOrderByLaboratoryService implements PurchaseOrderByLaboratoryServiceInterface
{
    /**
     * Construye la consulta base agregada por laboratorio.
     */
    protected function buildAggregatedQuery(array $filters): Builder
    {
        $query = AutoOrderDetail::query()
            ->join('auto_orders', 'auto_orders.id', '=', 'auto_order_details.order_id')
            ->join('products', 'products.id', '=', 'auto_order_details.product_id')
            ->leftJoin('laboratories', 'laboratories.id', '=', 'products.laboratory_id')
            ->whereNull('auto_orders.deleted_at')
            ->whereNull('auto_order_details.deleted_at')
            ->where('auto_order_details.quantity', '>', 0);

        // Filtro por estado de la orden
        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $query->where('auto_orders.status', (int) $filters['status']);
        }

        // Filtro por rango de fechas
        if (!empty($filters['start_date'])) {
            $query->whereDate('auto_orders.created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('auto_orders.created_at', '<=', $filters['end_date']);
        }

        // Filtro por buscador (laboratorio o producto)
        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('laboratories.name', 'like', "%{$search}%")
                  ->orWhere('products.name', 'like', "%{$search}%")
                  ->orWhere('products.barcode', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Obtiene el listado paginado de órdenes agrupadas por laboratorio.
     */
    public function getAggregatedLaboratories(array $filters): LengthAwarePaginator
    {
        $query = $this->buildAggregatedQuery($filters);

        $query->select([
            DB::raw('COALESCE(products.laboratory_id, 0) as laboratory_id'),
            DB::raw("COALESCE(laboratories.name, 'Sin Laboratorio') as laboratory_name"),
            DB::raw('COUNT(DISTINCT auto_order_details.product_id) as total_skus'),
            DB::raw('SUM(auto_order_details.quantity) as total_units'),
            DB::raw('SUM(auto_order_details.subtotal) as total_amount_usd'),
        ])
        ->groupBy(DB::raw('COALESCE(products.laboratory_id, 0)'), DB::raw("COALESCE(laboratories.name, 'Sin Laboratorio')"));

        // Ordenamiento
        $sortBy = $filters['sortBy'] ?? 'total_amount_usd';
        $orderBy = strtolower($filters['orderBy'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $validSorts = [
            'laboratory_name' => 'laboratory_name',
            'total_skus' => 'total_skus',
            'total_units' => 'total_units',
            'total_amount_usd' => 'total_amount_usd',
        ];

        $orderColumn = $validSorts[$sortBy] ?? 'total_amount_usd';
        $query->orderBy($orderColumn, $orderBy);

        $itemsPerPage = (int) ($filters['itemsPerPage'] ?? 10);
        $page = (int) ($filters['page'] ?? 1);

        if ($itemsPerPage === -1) {
            $results = $query->get();
            return new ConcretePaginator(
                $results,
                $results->count(),
                $results->count() > 0 ? $results->count() : 1,
                1,
                ['path' => ConcretePaginator::resolveCurrentPath()]
            );
        }

        return $query->paginate($itemsPerPage, ['*'], 'page', $page);
    }

    /**
     * Obtiene los ítems detallados de un laboratorio específico.
     */
    public function getLaboratoryDetails(int|string $laboratoryId, array $filters): LengthAwarePaginator
    {
        $query = AutoOrderDetail::query()
            ->join('auto_orders', 'auto_orders.id', '=', 'auto_order_details.order_id')
            ->join('products', 'products.id', '=', 'auto_order_details.product_id')
            ->whereNull('auto_orders.deleted_at')
            ->whereNull('auto_order_details.deleted_at')
            ->where('auto_order_details.quantity', '>', 0);

        if ((int) $laboratoryId === 0) {
            $query->whereNull('products.laboratory_id');
        } else {
            $query->where('products.laboratory_id', (int) $laboratoryId);
        }

        // Filtro por estado de la orden
        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $query->where('auto_orders.status', (int) $filters['status']);
        }

        // Filtro por rango de fechas
        if (!empty($filters['start_date'])) {
            $query->whereDate('auto_orders.created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->whereDate('auto_orders.created_at', '<=', $filters['end_date']);
        }

        // Filtro por buscador
        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                  ->orWhere('products.barcode', 'like', "%{$search}%");
            });
        }

        // Eager load relaciones necesarias para evitar N+1
        $query->with([
            'product:id,name,barcode,laboratory_id',
            'product.laboratory:id,name',
            'order:id,supplier_id,status,created_at',
            'order.supplier:id,name',
            'productSupplier:id,supplier_id,product_id',
            'productSupplier.supplier:id,name',
        ])
        ->select('auto_order_details.*')
        ->orderByDesc('auto_order_details.subtotal');

        $itemsPerPage = (int) ($filters['itemsPerPage'] ?? 10);
        $page = (int) ($filters['page'] ?? 1);

        if ($itemsPerPage === -1) {
            $results = $query->get();
            return new ConcretePaginator(
                $results,
                $results->count(),
                $results->count() > 0 ? $results->count() : 1,
                1,
                ['path' => ConcretePaginator::resolveCurrentPath()]
            );
        }

        return $query->paginate($itemsPerPage, ['*'], 'page', $page);
    }

    /**
     * Obtiene los KPIs estadísticos consolidados por laboratorio.
     */
    public function getStats(array $filters): array
    {
        // 1. Estadísticas globales (todos los estados)
        $globalFilters = $filters;
        unset($globalFilters['status']);

        $globalQuery = $this->buildAggregatedQuery($globalFilters);
        $globalTotals = $globalQuery->select([
            DB::raw('COUNT(DISTINCT products.laboratory_id) as total_laboratories'),
            DB::raw('COUNT(DISTINCT auto_order_details.product_id) as total_skus'),
            DB::raw('SUM(auto_order_details.quantity) as total_units'),
            DB::raw('SUM(auto_order_details.subtotal) as total_amount'),
        ])->first();

        // 2. Conteo por estado de órdenes (Pendiente 0, Enviada 1, Completada 2)
        $pendingQuery = $this->buildAggregatedQuery(array_merge($filters, ['status' => 0]));
        $pendingLaboratories = $pendingQuery->distinct('products.laboratory_id')->count('products.laboratory_id');

        $sentQuery = $this->buildAggregatedQuery(array_merge($filters, ['status' => 1]));
        $sentLaboratories = $sentQuery->distinct('products.laboratory_id')->count('products.laboratory_id');

        $completedQuery = $this->buildAggregatedQuery(array_merge($filters, ['status' => 2]));
        $completedLaboratories = $completedQuery->distinct('products.laboratory_id')->count('products.laboratory_id');

        return [
            'total_laboratories' => (int) ($globalTotals->total_laboratories ?? 0),
            'total_skus' => (int) ($globalTotals->total_skus ?? 0),
            'total_units' => (int) ($globalTotals->total_units ?? 0),
            'total_amount' => (float) ($globalTotals->total_amount ?? 0.0),
            'pending_orders' => $pendingLaboratories,
            'sent_orders' => $sentLaboratories,
            'completed_orders' => $completedLaboratories,
        ];
    }

    /**
     * Obtiene los datos para exportación de un laboratorio.
     */
    public function getExportData(int|string $laboratoryId, array $filters): Collection
    {
        $filters['itemsPerPage'] = -1;
        $paginated = $this->getLaboratoryDetails($laboratoryId, $filters);
        return collect($paginated->items());
    }
}
