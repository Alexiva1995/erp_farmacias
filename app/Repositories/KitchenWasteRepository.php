<?php

namespace App\Repositories;

use App\Contracts\Repositories\KitchenWasteRepositoryInterface;
use App\Enums\KitchenWasteReasonEnum;
use App\Enums\KitchenWasteStatusEnum;
use App\Models\KitchenWaste;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class KitchenWasteRepository implements KitchenWasteRepositoryInterface
{
    /**
     * Aplica filtros comunes (fecha, estación, motivo, tipo de ítem y búsqueda) a la consulta.
     */
    protected function applyCommonFilters(Builder $query, array $filters = [], bool $includeStatus = true): Builder
    {
        if ($includeStatus && !empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['reason']) && $filters['reason'] !== 'all') {
            $query->where('reason', $filters['reason']);
        }

        if (!empty($filters['item_type']) && $filters['item_type'] !== 'all') {
            $query->where('item_type', $filters['item_type']);
        }

        if (!empty($filters['kitchen_station_id']) && $filters['kitchen_station_id'] !== 'all') {
            $query->where('kitchen_station_id', (int) $filters['kitchen_station_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate(DB::raw('COALESCE(occurred_at, created_at)'), '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate(DB::raw('COALESCE(occurred_at, created_at)'), '<=', $filters['date_to']);
        }

        if (!empty($filters['q'])) {
            $search = trim((string) $filters['q']);
            $query->where(function ($sub) use ($search) {
                if (is_numeric($search)) {
                    $sub->where('product_id', (int) $search)
                        ->orWhere('dish_id', (int) $search)
                        ->orWhere('id', (int) $search);
                }
                $sub->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($prodQuery) use ($search) {
                        $prodQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('dish', function ($dishQuery) use ($search) {
                        $dishQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }

    /**
     * Obtiene el listado paginado de mermas evitando N+1 y seleccionando columnas explícitas.
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        KitchenWaste::ensureApprovalColumns();

        $query = KitchenWaste::query()
            ->select([
                'id',
                'user_id',
                'kitchen_station_id',
                'item_type',
                'product_id',
                'dish_id',
                'lot_id',
                'quantity',
                'unit_cost',
                'total_loss_usd',
                'reason',
                'notes',
                'status',
                'photo_path',
                'approved_by',
                'approved_at',
                'rejection_reason',
                'occurred_at',
                'created_at',
            ])
            ->with([
                'user:id,username',
                'approver:id,username',
                'kitchenStation:id,name',
                'product:id,name,stock,unit_of_measure,unit_cost',
                'dish:id,name,status,cost_price,cpv,output_product_id,expected_yield_quantity',
                'dish.ingredients:id,name,stock,unit_of_measure,unit_cost',
                'lot:id,product_id,lot_number,quantity',
            ]);

        $this->applyCommonFilters($query, $filters, true);

        // Ordenar pendientes primero si no se filtra por un estado específico, luego más recientes
        $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END ASC")
            ->orderByDesc('id');

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->paginate($perPage);
    }

    /**
     * Obtiene contadores y totales por estado y categoría contable (Desperdicio Operativo vs Consumo Interno).
     */
    public function getSummaryMetrics(array $filters = []): array
    {
        KitchenWaste::ensureApprovalColumns();

        $baseQuery = KitchenWaste::query();
        $this->applyCommonFilters($baseQuery, $filters, false);

        $pendingCount = (clone $baseQuery)
            ->where('status', KitchenWasteStatusEnum::PENDING->value)
            ->count();

        $approvedCount = (clone $baseQuery)
            ->where('status', KitchenWasteStatusEnum::APPROVED->value)
            ->count();

        $rejectedCount = (clone $baseQuery)
            ->where('status', KitchenWasteStatusEnum::REJECTED->value)
            ->count();

        $totalLossApprovedUsd = (float) (clone $baseQuery)
            ->where('status', KitchenWasteStatusEnum::APPROVED->value)
            ->sum('total_loss_usd');

        $totalLossPendingUsd = (float) (clone $baseQuery)
            ->where('status', KitchenWasteStatusEnum::PENDING->value)
            ->sum('total_loss_usd');

        $operationalReasons = KitchenWasteReasonEnum::operationalValues();
        $internalReasons = KitchenWasteReasonEnum::internalConsumptionValues();

        $operationalLossApprovedUsd = (float) (clone $baseQuery)
            ->where('status', KitchenWasteStatusEnum::APPROVED->value)
            ->whereIn('reason', $operationalReasons)
            ->sum('total_loss_usd');

        $operationalApprovedCount = (clone $baseQuery)
            ->where('status', KitchenWasteStatusEnum::APPROVED->value)
            ->whereIn('reason', $operationalReasons)
            ->count();

        $internalConsumptionApprovedUsd = (float) (clone $baseQuery)
            ->where('status', KitchenWasteStatusEnum::APPROVED->value)
            ->whereIn('reason', $internalReasons)
            ->sum('total_loss_usd');

        $internalApprovedCount = (clone $baseQuery)
            ->where('status', KitchenWasteStatusEnum::APPROVED->value)
            ->whereIn('reason', $internalReasons)
            ->count();

        return [
            'pending_count' => $pendingCount,
            'approved_count' => $approvedCount,
            'rejected_count' => $rejectedCount,
            'total_loss_approved_usd' => round($totalLossApprovedUsd, 2),
            'total_loss_pending_usd' => round($totalLossPendingUsd, 2),
            'operational_loss_approved_usd' => round($operationalLossApprovedUsd, 2),
            'operational_approved_count' => $operationalApprovedCount,
            'internal_consumption_approved_usd' => round($internalConsumptionApprovedUsd, 2),
            'internal_approved_count' => $internalApprovedCount,
        ];
    }

    /**
     * Crea un registro de merma y carga sus relaciones.
     */
    public function create(array $data): KitchenWaste
    {
        KitchenWaste::ensureApprovalColumns();

        $waste = KitchenWaste::create($data);

        return $waste->load([
            'user:id,username',
            'approver:id,username',
            'kitchenStation:id,name',
            'product:id,name,stock,unit_of_measure,unit_cost',
            'dish:id,name,status,cost_price,cpv,output_product_id,expected_yield_quantity',
            'dish.ingredients:id,name,stock,unit_of_measure,unit_cost',
            'lot:id,product_id,lot_number,quantity',
        ]);
    }

    /**
     * Actualiza un registro de merma y recarga sus relaciones.
     */
    public function update(KitchenWaste $waste, array $data): KitchenWaste
    {
        KitchenWaste::ensureApprovalColumns();

        $waste->update($data);

        return $waste->refresh()->load([
            'user:id,username',
            'approver:id,username',
            'kitchenStation:id,name',
            'product:id,name,stock,unit_of_measure,unit_cost',
            'dish:id,name,status,cost_price,cpv,output_product_id,expected_yield_quantity',
            'dish.ingredients:id,name,stock,unit_of_measure,unit_cost',
            'lot:id,product_id,lot_number,quantity',
        ]);
    }
}
