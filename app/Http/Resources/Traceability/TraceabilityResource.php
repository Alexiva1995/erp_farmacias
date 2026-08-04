<?php

namespace App\Http\Resources\Traceability;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TraceabilityResource extends JsonResource
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
            'product_id' => $this->product_id,
            'movement_type' => $this->movement_type,
            'quantity' => (float) $this->quantity,
            'stock_before' => (float) ($this->stock_before ?? 0),
            'stock_after' => (float) ($this->stock_after ?? 0),
            'global_stock_before' => isset($this->global_stock_before) ? (float) $this->global_stock_before : (float) ($this->stock_before ?? 0),
            'global_stock_after' => isset($this->global_stock_after) ? (float) $this->global_stock_after : (float) ($this->stock_after ?? 0),
            'movement_date' => $this->movement_date ? $this->movement_date->toIso8601String() : null,
            'order_id' => $this->order_id,
            'invoice_id' => $this->invoice_id,
            'product_lot_id' => $this->product_lot_id,

            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'photo_url' => $this->product->photo_url,
                    'psychotropic' => (bool) $this->product->psychotropic,
                    'iva' => (bool) $this->product->iva,
                    'is_colombian_origin' => (bool) $this->product->is_colombian_origin,
                    'active_ingredient' => $this->product->active_ingredient,
                    'presentation' => $this->product->presentation,
                    'unit_of_measure' => $this->product->unit_of_measure,
                    'laboratory' => $this->product->relationLoaded('laboratory') && $this->product->laboratory ? [
                        'id' => $this->product->laboratory->id,
                        'name' => $this->product->laboratory->name,
                    ] : null,
                ];
            }),

            'dish' => $this->whenLoaded('dish', function () {
                return $this->dish ? [
                    'id' => $this->dish->id,
                    'name' => $this->dish->name,
                ] : null;
            }),

            'user' => $this->whenLoaded('user', function () {
                return $this->user ? [
                    'id' => $this->user->id,
                    'username' => $this->user->username,
                    'email' => $this->user->email,
                    'employee' => $this->user->relationLoaded('employee') && $this->user->employee ? [
                        'name' => $this->user->employee->name,
                        'last_name' => $this->user->employee->last_name,
                    ] : null,
                ] : null;
            }),

            'invoice' => $this->whenLoaded('invoice', function () {
                return $this->invoice ? [
                    'id' => $this->invoice->id,
                    'invoice_number' => $this->invoice->invoice_number,
                    'supplier' => $this->invoice->relationLoaded('supplier') && $this->invoice->supplier ? [
                        'name' => $this->invoice->supplier->name,
                    ] : null,
                ] : null;
            }),
        ];
    }
}
