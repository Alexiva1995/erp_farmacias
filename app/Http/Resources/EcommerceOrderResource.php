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
        $res = $this->resource;

        return [
            'id'                       => data_get($res, 'id'),
            'user_id'                  => data_get($res, 'user_id'),
            'assigned_user'            => data_get($res, 'assigned_user'),
            'customer_name'            => data_get($res, 'customer_name'),
            'customer_email'           => data_get($res, 'customer_email'),
            'customer_phone'           => data_get($res, 'customer_phone'),
            'customer_document_type'   => data_get($res, 'customer_document_type'),
            'customer_document_number' => data_get($res, 'customer_document_number'),
            'shipping_address'         => data_get($res, 'shipping_address'),
            'total_amount'             => (float) (data_get($res, 'total_amount') ?? 0),
            'currency'                 => data_get($res, 'currency') ?? 'USD',
            'total_in_currency'        => (float) (data_get($res, 'total_in_currency') ?? data_get($res, 'total_amount') ?? 0),
            'status'                   => data_get($res, 'status'),
            'payment_method'           => data_get($res, 'payment_method'),
            'tpv_order_id'             => data_get($res, 'tpv_order_id'),
            'created_at'               => data_get($res, 'created_at') ? (string) data_get($res, 'created_at') : null,
            'updated_at'               => data_get($res, 'updated_at') ? (string) data_get($res, 'updated_at') : null,
            'items'                    => data_get($res, 'items') ?? [],
        ];
    }
}
