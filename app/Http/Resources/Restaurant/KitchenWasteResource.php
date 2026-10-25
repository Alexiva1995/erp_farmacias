<?php

namespace App\Http\Resources\Restaurant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KitchenWasteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_type' => $this->item_type,
            'item_name' => $this->item_type === 'product' ? $this->product?->name : $this->dish?->name,
            'quantity' => (float) $this->quantity,
            'unit_cost' => (float) $this->unit_cost,
            'total_loss_usd' => (float) $this->total_loss_usd,
            'reason' => is_object($this->reason) ? $this->reason->value : $this->reason,
            'reason_label' => is_object($this->reason) ? $this->reason->label() : $this->reason,
            'station_name' => $this->kitchenStation?->name ?? 'General',
            'user_name' => $this->user?->name ?? $this->user?->username,
            'notes' => $this->notes,
            'occurred_at' => $this->occurred_at?->format('d/m/Y H:i'),
        ];
    }
}
