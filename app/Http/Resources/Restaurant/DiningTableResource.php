<?php

namespace App\Http\Resources\Restaurant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiningTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dining_zone_id' => $this->dining_zone_id,
            'name' => $this->name,
            'capacity' => $this->capacity,
            'shape' => is_object($this->shape) ? $this->shape->value : $this->shape,
            'pos_x' => (float) $this->pos_x,
            'pos_y' => (float) $this->pos_y,
            'width' => (float) $this->width,
            'height' => (float) $this->height,
            'rotation' => (float) $this->rotation,
            'status' => is_object($this->status) ? $this->status->value : $this->status,
            'status_label' => is_object($this->status) ? $this->status->label() : $this->status,
            'current_order_id' => $this->current_order_id,
            'current_order' => $this->currentOrder ? [
                'id' => $this->currentOrder->id,
                'total_amount' => (float) $this->currentOrder->total_amount,
                'total_amount_usd' => (float) $this->currentOrder->total_amount_usd,
                'service_charge_percentage' => (float) $this->currentOrder->service_charge_percentage,
                'service_charge_amount_usd' => (float) $this->currentOrder->service_charge_amount_usd,
                'guests_count' => $this->currentOrder->guests_count,
                'seller_name' => $this->currentOrder->seller?->name ?? $this->currentOrder->seller?->username ?? 'Mesero',
                'client_name' => $this->currentOrder->client?->name ?? 'Cliente',
                'order_date' => $this->currentOrder->order_date?->format('H:i'),
                'items_count' => $this->currentOrder->details ? $this->currentOrder->details->count() : 0,
                'details' => $this->currentOrder->details ? $this->currentOrder->details->map(function ($d) {
                    return [
                        'id' => $d->id,
                        'dish_id' => $d->dish_id,
                        'name' => $d->dish?->name ?? $d->notes ?? 'Ítem',
                        'seat_number' => $d->seat_number,
                        'quantity' => (float) $d->quantity,
                        'price' => (float) $d->price,
                        'unit_price_usd' => (float) $d->unit_price_usd,
                        'kitchen_status' => is_object($d->kitchen_status) ? $d->kitchen_status->value : $d->kitchen_status,
                    ];
                }) : [],
            ] : null,
            'is_active' => $this->is_active,
        ];
    }
}
