<?php

namespace App\Services;

use App\Models\ProcessAudit;
use App\Models\ProcessFlow;
use App\Models\ProcessFlowPhase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProcessAuditService
{
    /**
     * Obtener historial paginado de auditorías de procesos.
     */
    public function index(array $filters): LengthAwarePaginator
    {
        $perPage = $filters['perPage'] ?? 10;
        
        $query = ProcessAudit::with(['order', 'cashier', 'cook', 'flow', 'phases.flowPhase'])
            ->orderBy('id', 'desc');

        if (!empty($filters['flow_id'])) {
            $query->where('flow_id', $filters['flow_id']);
        }

        if (!empty($filters['startDate'])) {
            $query->whereDate('created_at', '>=', $filters['startDate']);
        }

        if (!empty($filters['endDate'])) {
            $query->whereDate('created_at', '<=', $filters['endDate']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Crear un nuevo registro de auditoría con sus fases dinámicas.
     */
    public function store(array $data): ProcessAudit
    {
        return DB::transaction(function () use ($data) {
            $audit = ProcessAudit::create([
                'flow_id' => $data['flow_id'],
                'order_id' => $data['order_id'] ?? null,
                'cashier_id' => $data['cashier_id'] ?? null,
                'cook_id' => $data['cook_id'],
                'total_seconds' => $data['total_seconds'],
            ]);

            foreach ($data['phases'] as $phaseData) {
                $audit->phases()->create([
                    'flow_phase_id' => $phaseData['flow_phase_id'],
                    'seconds' => $phaseData['seconds'],
                ]);
            }

            return $audit->load(['order', 'cashier', 'cook', 'flow', 'phases.flowPhase']);
        });
    }

    /**
     * Listar todos los flujos activos de procesos.
     */
    public function listFlows(): \Illuminate\Database\Eloquent\Collection
    {
        return ProcessFlow::with('phases')->where('is_active', true)->get();
    }

    /**
     * Guardar o actualizar un flujo de proceso completo con sus fases.
     */
    public function storeFlow(array $data): ProcessFlow
    {
        return DB::transaction(function () use ($data) {
            $flow = ProcessFlow::updateOrCreate(
                ['id' => $data['id'] ?? null],
                [
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'is_active' => $data['is_active'] ?? true,
                ]
            );

            // Eliminar fases anteriores para recrearlas u ordenarlas
            $flow->phases()->delete();

            foreach ($data['phases'] as $index => $phaseData) {
                $flow->phases()->create([
                    'name' => $phaseData['name'],
                    'description' => $phaseData['description'] ?? null,
                    'sort_order' => $phaseData['sort_order'] ?? $index,
                ]);
            }

            return $flow->load('phases');
        });
    }

    /**
     * Eliminar un flujo de procesos.
     */
    public function deleteFlow(int $id): bool
    {
        $flow = ProcessFlow::findOrFail($id);
        return $flow->delete();
    }
}
