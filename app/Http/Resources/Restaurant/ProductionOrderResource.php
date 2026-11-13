<?php

namespace App\Http\Resources\Restaurant;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductionOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statusValue = is_object($this->status) ? $this->status->value : (string) $this->status;
        $statusLabel = is_object($this->status) ? $this->status->label() : match ($statusValue) {
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
            'in_progress' => 'En Proceso',
            'draft' => 'Borrador',
            default => $statusValue,
        };
        $statusColor = match ($statusValue) {
            'completed' => 'success',
            'cancelled' => 'error',
            'in_progress' => 'info',
            default => 'warning',
        };

        $expectedQty = (float) ($this->expected_quantity ?: 1.0);
        $actualQty = (float) $this->actual_quantity;
        $yieldEfficiencyPct = $expectedQty > 0
            ? round(($actualQty / $expectedQty) * 100, 1)
            : 100.0;

        $expirationDate = $this->generatedLot?->expiration_date
            ? Carbon::parse($this->generatedLot->expiration_date)->format('d/m/Y')
            : null;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'dish' => [
                'id' => $this->dish?->id,
                'name' => $this->dish?->name,
                'expected_yield_quantity' => (float) ($this->dish?->expected_yield_quantity ?: 1.0),
                'shelf_life_days' => (int) ($this->dish?->shelf_life_days ?: 7),
            ],
            'output_product' => [
                'id' => $this->outputProduct?->id,
                'name' => $this->outputProduct?->name,
                'unit_of_measure' => $this->outputProduct?->unit_of_measure ?? 'porción',
                'stock' => (float) ($this->outputProduct?->stock ?? 0),
                'unit_cost' => (float) ($this->outputProduct?->unit_cost ?? 0),
            ],
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name ?? $this->user?->username ?? 'Cocina',
            ],
            'batch_multiplier' => (float) $this->batch_multiplier,
            'expected_quantity' => $expectedQty,
            'actual_quantity' => $actualQty,
            'yield_efficiency_pct' => $yieldEfficiencyPct,
            'total_production_cost' => (float) $this->total_production_cost,
            'unit_cost_calculated' => (float) $this->unit_cost_calculated,
            'lot_number' => $this->generatedLot?->lot_number,
            'expiration_date' => $expirationDate,
            'lot_remaining_qty' => (float) ($this->generatedLot?->quantity ?? 0),
            'status' => $statusValue,
            'status_label' => $statusLabel,
            'status_color' => $statusColor,
            'notes' => $this->notes,
            'produced_at' => $this->produced_at?->format('d/m/Y H:i'),
        ];
    }
}
