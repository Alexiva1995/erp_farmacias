<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\InventorySnapshotRepositoryInterface;
use App\Models\InventorySnapshot;
use App\Models\InventorySnapshotItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventorySnapshotRepository implements InventorySnapshotRepositoryInterface
{
    /**
     * Obtener lista paginada de snapshots históricos.
     */
    public function paginateSnapshots(array $filters): LengthAwarePaginator
    {
        $query = InventorySnapshot::query()->with('creator:id,username');

        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('cutoff_date', 'like', $term);
            });
        }

        $allowedSorts = [
            'id', 'name', 'cutoff_date', 'period_days', 'total_products',
            'total_inventory_units', 'total_inventory_value', 'total_sales_units',
            'total_sales_value', 'overstock_products_count', 'overstock_inventory_value', 'created_at',
        ];

        $rawSortBy = is_array($filters['sortBy'] ?? null) 
            ? ($filters['sortBy'][0]['key'] ?? 'cutoff_date') 
            : ($filters['sortBy'] ?? 'cutoff_date');
        $sortBy = in_array($rawSortBy, $allowedSorts, true) ? $rawSortBy : 'cutoff_date';

        $rawOrderBy = is_array($filters['sortBy'] ?? null) 
            ? ($filters['sortBy'][0]['order'] ?? 'desc') 
            : ($filters['orderBy'] ?? 'desc');
        $orderBy = strtolower((string) $rawOrderBy) === 'asc' ? 'asc' : 'desc';
        $perPage = max(1, (int) ($filters['itemsPerPage'] ?? 10));

        return $query->orderBy($sortBy, $orderBy)->paginate($perPage);
    }

    /**
     * Obtener un snapshot por ID con sus ítems filtrados y paginados.
     */
    public function getSnapshotWithItems(int $snapshotId, array $filters): array
    {
        $snapshot = InventorySnapshot::with('creator:id,username')->findOrFail($snapshotId);

        $itemsQuery = InventorySnapshotItem::query()
            ->where('inventory_snapshot_id', $snapshotId);

        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $itemsQuery->where(function ($q) use ($term) {
                $q->where('product_name', 'like', $term)
                  ->orWhere('laboratory_name', 'like', $term)
                  ->orWhere('product_id', 'like', $term);
            });
        }

        if (!empty($filters['sales_class'])) {
            $itemsQuery->where('sales_class', strtoupper((string) $filters['sales_class']));
        }

        if (isset($filters['is_overstock']) && $filters['is_overstock'] !== '' && $filters['is_overstock'] !== null) {
            $isOverstock = filter_var($filters['is_overstock'], FILTER_VALIDATE_BOOLEAN);
            $itemsQuery->where('is_overstock', $isOverstock);
        }

        $columnMap = [
            'id' => 'id',
            'id_producto' => 'product_id',
            'product_id' => 'product_id',
            'nombre_producto' => 'product_name',
            'product_name' => 'product_name',
            'laboratorio' => 'laboratory_name',
            'laboratory_name' => 'laboratory_name',
            'clasificacion_ventas' => 'sales_class',
            'sales_class' => 'sales_class',
            'ventas_unidades_30d' => 'sold_units_30d',
            'sold_units_30d' => 'sold_units_30d',
            'ventas_totales_usd_30d' => 'total_sales_usd_30d',
            'total_sales_usd_30d' => 'total_sales_usd_30d',
            'stock_actual_unidades' => 'current_stock_units',
            'current_stock_units' => 'current_stock_units',
            'costo_unitario_usd' => 'unit_cost_usd',
            'unit_cost_usd' => 'unit_cost_usd',
            'precio_venta_usd' => 'sale_price_usd',
            'sale_price_usd' => 'sale_price_usd',
            'valor_inventario_usd' => 'inventory_value_usd',
            'inventory_value_usd' => 'inventory_value_usd',
            'margen_porcentaje' => 'margin_percentage',
            'margin_percentage' => 'margin_percentage',
            'cobertura_dias' => 'coverage_days',
            'coverage_days' => 'coverage_days',
            'gmroi_anual_porcentaje' => 'gmroi_annual_percentage',
            'gmroi_annual_percentage' => 'gmroi_annual_percentage',
            'dias_para_vencer' => 'days_to_expiration',
            'days_to_expiration' => 'days_to_expiration',
            'es_sobrestock' => 'is_overstock',
            'is_overstock' => 'is_overstock',
        ];

        $rawSortKey = is_array($filters['sortBy'] ?? null) 
            ? ($filters['sortBy'][0]['key'] ?? 'inventory_value_usd') 
            : ($filters['sortBy'] ?? 'inventory_value_usd');
        
        $sortBy = $columnMap[$rawSortKey] ?? 'inventory_value_usd';

        $rawOrderBy = is_array($filters['sortBy'] ?? null) 
            ? ($filters['sortBy'][0]['order'] ?? 'desc') 
            : ($filters['orderBy'] ?? 'desc');
        $orderBy = strtolower((string) $rawOrderBy) === 'asc' ? 'asc' : 'desc';
        $perPage = max(1, (int) ($filters['itemsPerPage'] ?? 15));

        $itemsPaginator = $itemsQuery->orderBy($sortBy, $orderBy)->paginate($perPage);

        return [
            'snapshot' => $snapshot,
            'items' => $itemsPaginator,
        ];
    }

    /**
     * Guardar un nuevo snapshot con sus ítems en base de datos en una transacción.
     */
    public function createSnapshot(array $headerData, array $itemsData): InventorySnapshot
    {
        return DB::transaction(function () use ($headerData, $itemsData) {
            $snapshot = InventorySnapshot::create($headerData);

            // Inserción en bloques de 500 registros para alta eficiencia
            $now = now();
            $chunks = array_chunk($itemsData, 500);

            foreach ($chunks as $chunk) {
                $formattedChunk = array_map(function ($item) use ($snapshot, $now) {
                    $item['inventory_snapshot_id'] = $snapshot->id;
                    $item['created_at'] = $now;
                    $item['updated_at'] = $now;
                    return $item;
                }, $chunk);

                InventorySnapshotItem::insert($formattedChunk);
            }

            return $snapshot;
        });
    }

    /**
     * Eliminar un snapshot histórico por ID.
     */
    public function deleteSnapshot(int $snapshotId): bool
    {
        $snapshot = InventorySnapshot::findOrFail($snapshotId);
        return (bool) $snapshot->delete();
    }

    /**
     * Obtener todos los ítems de un snapshot para exportación a Excel.
     */
    public function getSnapshotItemsForExport(int $snapshotId): Collection
    {
        return InventorySnapshotItem::query()
            ->where('inventory_snapshot_id', $snapshotId)
            ->orderBy('inventory_value_usd', 'desc')
            ->get();
    }
}
