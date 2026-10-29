<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryBookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period_type' => $this->period_type?->value ?? 'fortnightly',
            'year' => $this->year,
            'month' => $this->month,
            'fortnight' => $this->fortnight,
            'period_label' => $this->fortnight
                ? "Quincena {$this->fortnight} - " . str_pad((string)$this->month, 2, '0', STR_PAD_LEFT) . "/{$this->year}"
                : "Mes " . str_pad((string)$this->month, 2, '0', STR_PAD_LEFT) . "/{$this->year}",
            'start_date' => $this->start_date ? $this->start_date->format('Y-m-d') : null,
            'end_date' => $this->end_date ? $this->end_date->format('Y-m-d') : null,
            'status' => $this->status?->value ?? 'open',
            'is_open' => $this->isOpen(),
            'is_closed' => $this->isClosed(),
            'include_negative_adjustments_as_withdrawals' => (bool) $this->include_negative_adjustments_as_withdrawals,
            'entries_count' => $this->relationLoaded('entries') ? $this->entries->count() : null,
            'total_initial_units' => (float) $this->total_initial_units,
            'total_purchase_units' => (float) $this->total_purchase_units,
            'total_sales_units' => (float) $this->total_sales_units,
            'total_withdrawal_units' => (float) $this->total_withdrawal_units,
            'total_self_consumption_units' => (float) $this->total_self_consumption_units,
            'total_final_units' => (float) $this->total_final_units,
            'total_previous_value_bs' => (float) $this->total_previous_value_bs,
            'total_purchase_cost_bs' => (float) $this->total_purchase_cost_bs,
            'total_sales_cost_bs' => (float) $this->total_sales_cost_bs,
            'total_withdrawal_cost_bs' => (float) $this->total_withdrawal_cost_bs,
            'total_self_consumption_cost_bs' => (float) $this->total_self_consumption_cost_bs,
            'total_final_inventory_value_bs' => (float) $this->total_final_inventory_value_bs,
            'total_withdrawal_exempt_bs' => (float) $this->total_withdrawal_exempt_bs,
            'total_withdrawal_taxable_bs' => (float) $this->total_withdrawal_taxable_bs,
            'total_withdrawal_vat_bs' => (float) $this->total_withdrawal_vat_bs,
            'closed_at' => $this->closed_at?->toISOString(),
            'closed_by' => $this->closedByUser ? [
                'id' => $this->closedByUser->id,
                'name' => $this->closedByUser->name,
            ] : null,
            'entries' => InventoryBookEntryResource::collection($this->whenLoaded('entries')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
