<?php

namespace App\Http\Resources\Restaurant;

use App\Enums\KitchenWasteStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KitchenWasteResource extends JsonResource
{
    /**
     * Transforma el registro de merma en un arreglo estandarizado para la API.
     */
    public function toArray(Request $request): array
    {
        $statusValue = is_object($this->status) ? $this->status->value : ($this->status ?: KitchenWasteStatusEnum::PENDING->value);
        $statusEnum = KitchenWasteStatusEnum::tryFrom((string) $statusValue) ?? KitchenWasteStatusEnum::PENDING;

        $photoUrl = null;
        if (!empty($this->photo_path)) {
            $photoUrl = str_starts_with($this->photo_path, 'http')
                ? $this->photo_path
                : asset('storage/' . ltrim($this->photo_path, '/'));
        }

        return [
            'id' => $this->id,
            'item_type' => $this->item_type,
            'product_id' => $this->product_id,
            'dish_id' => $this->dish_id,
            'lot_id' => $this->lot_id,
            'lot_number' => $this->lot?->lot_number,
            'item_name' => $this->item_type === 'product'
                ? ($this->product?->name ?? "Producto #{$this->product_id}")
                : ($this->dish?->name ?? "Platillo #{$this->dish_id}"),
            'product_unit' => $this->product?->unit_of_measure ?? 'und',
            'current_stock' => (float) ($this->product?->stock ?? 0),
            'quantity' => (float) $this->quantity,
            'unit_cost' => (float) $this->unit_cost,
            'total_loss_usd' => (float) $this->total_loss_usd,
            'reason' => is_object($this->reason) ? $this->reason->value : $this->reason,
            'reason_label' => is_object($this->reason) ? $this->reason->label() : $this->reason,
            'station_name' => $this->kitchenStation?->name ?? 'General',
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name ?? $this->user?->username ?? 'Usuario',
            'notes' => $this->notes,
            'status' => $statusEnum->value,
            'status_label' => $statusEnum->label(),
            'status_color' => $statusEnum->color(),
            'photo_path' => $this->photo_path,
            'photo_url' => $photoUrl,
            'approved_by' => $this->approved_by,
            'approved_by_name' => $this->approver?->name ?? $this->approver?->username,
            'approved_at' => $this->approved_at?->format('d/m/Y H:i'),
            'rejection_reason' => $this->rejection_reason,
            'occurred_at' => $this->occurred_at?->format('d/m/Y H:i') ?? $this->created_at?->format('d/m/Y H:i'),
        ];
    }
}
