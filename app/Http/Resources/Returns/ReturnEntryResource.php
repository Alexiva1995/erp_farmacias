<?php

declare(strict_types=1);

namespace App\Http\Resources\Returns;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnEntryResource extends JsonResource
{
    /**
     * Transformar el recurso de devolución en un array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $refundMethod = $this->refund_method?->value ?? $this->refund_method ?? 'balance';
        $fiscalStatus = $this->fiscal_status?->value ?? $this->fiscal_status ?? 'not_applicable';

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'quantity' => (int) $this->quantity,
            'amount_refunded' => (float) $this->amount_refunded,
            'refund_method' => $refundMethod,
            'restock_inventory' => (bool) ($this->restock_inventory ?? true),
            'reason' => $this->reason,
            'return_date' => $this->return_date?->toIso8601String() ?? $this->return_date,
            'status' => $this->status,
            'fiscal_status' => $fiscalStatus,
            'credit_note_number' => $this->credit_note_number,
            'fiscal_command_id' => $this->fiscal_command_id,
            'generated_by_id' => $this->generated_by_id,
            'approved_by_id' => $this->approved_by_id,
            'generated_by' => $this->whenLoaded('generatedBy', fn () => [
                'id' => $this->generatedBy->id,
                'name' => $this->generatedBy->name,
                'username' => $this->generatedBy->username ?? $this->generatedBy->name,
            ]),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
                'username' => $this->approvedBy->username ?? $this->approvedBy->name,
            ]),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'active_ingredient' => $this->product->active_ingredient,
                'presentation' => $this->product->presentation,
                'laboratory' => $this->product->relationLoaded('laboratory') && $this->product->laboratory
                    ? [
                        'id' => $this->product->laboratory->id,
                        'name' => $this->product->laboratory->name,
                    ]
                    : null,
            ]),
            'order' => $this->whenLoaded('order', fn () => [
                'id' => $this->order->id,
                'currency' => $this->order->currency,
                'usd_conversion' => (float) ($this->order->usd_conversion ?? 1),
                'order_date' => $this->order->order_date?->toIso8601String(),
                'client' => $this->order->relationLoaded('client') && $this->order->client
                    ? [
                        'id' => $this->order->client->id,
                        'name' => $this->order->client->name,
                        'last_name' => $this->order->client->last_name,
                        'identification_type' => $this->order->client->identification_type,
                        'identification' => $this->order->client->identification,
                    ]
                    : null,
                'seller' => $this->order->relationLoaded('seller') && $this->order->seller
                    ? [
                        'id' => $this->order->seller->id,
                        'name' => $this->order->seller->name,
                        'username' => $this->order->seller->username ?? $this->order->seller->name,
                    ]
                    : null,
                'fiscal_history' => $this->order->relationLoaded('fiscalHistory') && $this->order->fiscalHistory
                    ? [
                        'id' => $this->order->fiscalHistory->id,
                        'invoice_number' => $this->order->fiscalHistory->invoice_number,
                        'fiscal_id' => $this->order->fiscalHistory->fiscal_id,
                    ]
                    : null,
            ]),
        ];
    }
}
