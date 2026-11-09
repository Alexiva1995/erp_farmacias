<?php

namespace App\Repositories;

use App\Contracts\Repositories\KitchenWasteRepositoryInterface;
use App\Enums\KitchenWasteStatusEnum;
use App\Models\KitchenWaste;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class KitchenWasteRepository implements KitchenWasteRepositoryInterface
{
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
                'user:id,username,name',
                'approver:id,username,name',
                'kitchenStation:id,name',
                'product:id,name,stock,unit_of_measure,unit_cost',
                'dish:id,name',
                'lot:id,product_id,lot_number,quantity',
            ]);

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['reason'])) {
            $query->where('reason', $filters['reason']);
        }

        if (!empty($filters['item_type'])) {
            $query->where('item_type', $filters['item_type']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['q'])) {
            $search = trim((string) $filters['q']);
            $query->where(function ($sub) use ($search) {
                if (is_numeric($search)) {
                    $sub->where('product_id', (int) $search)
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

        // Ordenar pendientes primero si no se filtra por un estado específico, luego más recientes
        $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END ASC")
            ->orderByDesc('id');

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->paginate($perPage);
    }

    /**
     * Obtiene contadores y totales por estado para las tarjetas resumen.
     */
    public function getSummaryMetrics(array $filters = []): array
    {
        KitchenWaste::ensureApprovalColumns();

        $baseQuery = KitchenWaste::query();

        if (!empty($filters['user_id'])) {
            $baseQuery->where('user_id', (int) $filters['user_id']);
        }

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

        return [
            'pending_count' => $pendingCount,
            'approved_count' => $approvedCount,
            'rejected_count' => $rejectedCount,
            'total_loss_approved_usd' => round($totalLossApprovedUsd, 2),
            'total_loss_pending_usd' => round($totalLossPendingUsd, 2),
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
            'user:id,username,name',
            'approver:id,username,name',
            'kitchenStation:id,name',
            'product:id,name,stock,unit_of_measure,unit_cost',
            'dish:id,name',
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
            'user:id,username,name',
            'approver:id,username,name',
            'kitchenStation:id,name',
            'product:id,name,stock,unit_of_measure,unit_cost',
            'dish:id,name',
            'lot:id,product_id,lot_number,quantity',
        ]);
    }
}
