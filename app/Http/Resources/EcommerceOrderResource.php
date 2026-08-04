<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EcommerceOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'user_id'                  => $this->user_id,
            'assigned_user'            => $this->assigned_user ?? null,
            'customer_name'            => $this->customer_name,
            'customer_email'           => $this->customer_email,
            'customer_phone'           => $this->customer_phone,
            'customer_document_type'   => $this->customer_document_type,
            'customer_document_number' => $this->customer_document_number,
            'shipping_address'         => $this->shipping_address,
            'total_amount'             => (float) $this->total_amount,
            'currency'                 => $this->currency ?? 'USD',
            'total_in_currency'        => (float) ($this->total_in_currency ?? $this->total_amount),
            'status'                   => $this->status,
            'payment_method'           => $this->payment_method,
            'tpv_order_id'             => $this->tpv_order_id,
            'created_at'               => $this->created_at ? (string) $this->created_at : null,
            'updated_at'               => $this->updated_at ? (string) $this->updated_at : null,
            'items'                    => $this->items ?? [],
        ];
    }
}
