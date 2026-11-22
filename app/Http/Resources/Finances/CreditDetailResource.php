<?php

namespace App\Http\Resources\Finances;

use App\Http\Resources\ClientResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditDetailResource extends JsonResource
{
    /**
     * Transforma el detalle individual de un crédito con su orden y productos.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'order_id' => $this->order_id,
            'credit_amount' => (float) $this->credit_amount,
            'pending_amount' => (float) $this->pending_amount,
            'credit_date' => $this->credit_date?->format('Y-m-d H:i:s') ?? (string) $this->credit_date,
            'next_due_date' => $this->next_due_date?->format('Y-m-d'),
            'status' => $this->status,
            'credit_type' => $this->credit_type,
            'invoice_number' => $this->invoice_number,
            'exchange_rate_issue' => $this->exchange_rate_issue !== null ? (float) $this->exchange_rate_issue : null,
            'client' => new ClientResource($this->whenLoaded('client')),
            'order' => $this->whenLoaded('order', function () {
                return [
                    'id' => $this->order->id,
                    'currency' => $this->order->currency,
                    'total_amount' => (float) $this->order->total_amount,
                    'created_at' => $this->order->created_at?->format('Y-m-d H:i:s'),
                    'details' => $this->order->relationLoaded('details')
                        ? $this->order->details->map(function ($detail) {
                            return [
                                'id' => $detail->id,
                                'product_id' => $detail->product_id,
                                'dish_id' => $detail->dish_id,
                                'product_type' => $detail->product_type,
                                'quantity' => (float) $detail->quantity,
                                'price' => (float) $detail->price,
                                'unit_price_usd' => (float) ($detail->unit_price_usd ?? $detail->price),
                                'product' => $detail->relationLoaded('product') && $detail->product ? [
                                    'id' => $detail->product->id,
                                    'name' => $detail->product->name,
                                    'laboratory' => $detail->product->relationLoaded('laboratory') && $detail->product->laboratory
                                        ? ['id' => $detail->product->laboratory->id, 'name' => $detail->product->laboratory->name]
                                        : null,
                                ] : null,
                                'dish' => $detail->relationLoaded('dish') && $detail->dish ? [
                                    'id' => $detail->dish->id,
                                    'name' => $detail->dish->name,
                                ] : null,
                            ];
                        })->values()
                        : [],
                ];
            }),
        ];
    }
}
