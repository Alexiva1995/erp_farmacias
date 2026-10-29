<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryBookEntryResource extends JsonResource
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
            'inventory_book_id' => $this->inventory_book_id,
            'product_id' => $this->product_id,
            'operation_number' => $this->operation_number,
            'product_code' => $this->product_code,
            'product_description' => $this->product_description,
            'unit_cost_bs' => (float) $this->unit_cost_bs,
            'initial_stock' => (float) $this->initial_stock,
            'purchase_entries' => (float) $this->purchase_entries,
            'sales_exits' => (float) $this->sales_exits,
            'withdrawals' => (float) $this->withdrawals,
            'self_consumptions' => (float) $this->self_consumptions,
            'final_stock' => (float) $this->final_stock,
            'previous_value_bs' => (float) $this->previous_value_bs,
            'purchase_entries_cost_bs' => (float) $this->purchase_entries_cost_bs,
            'sales_exits_cost_bs' => (float) $this->sales_exits_cost_bs,
            'withdrawals_cost_bs' => (float) $this->withdrawals_cost_bs,
            'self_consumptions_cost_bs' => (float) $this->self_consumptions_cost_bs,
            'final_inventory_value_bs' => (float) $this->final_inventory_value_bs,
            'iva_rate' => (float) $this->iva_rate,
            'withdrawal_vat_bs' => (float) $this->withdrawal_vat_bs,
            'withdrawal_reason' => $this->withdrawal_reason,
            'is_manual_override' => (bool) $this->is_manual_override,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
