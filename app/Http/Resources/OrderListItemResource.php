<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderListItemResource extends JsonResource
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
            'order_date' => $this->order_date ? (is_string($this->order_date) ? $this->order_date : $this->order_date->format('Y-m-d H:i:s')) : null,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_methods' => $this->payment_methods,
            'client' => $this->whenLoaded('client', function () {
                return [
                    'id' => $this->client->id,
                    'identification_type' => $this->client->identification_type,
                    'identification' => $this->client->identification,
                    'name' => $this->client->name,
                    'last_name' => $this->client->last_name,
                ];
            }),
            'seller' => $this->whenLoaded('seller', function () {
                return [
                    'id' => $this->seller->id,
                    'username' => $this->seller->username,
                ];
            }),
            'fiscal_history' => $this->whenLoaded('fiscalHistory', function () {
                return [
                    'id' => $this->fiscalHistory->id,
                    'invoice_number' => $this->fiscalHistory->invoice_number,
                    'exempt_amount' => (float) $this->fiscalHistory->exempt_amount,
                    'taxable_amount' => (float) $this->fiscalHistory->taxable_amount,
                    'iva_amount' => (float) $this->fiscalHistory->iva_amount,
                    'total_amount' => (float) $this->fiscalHistory->total_amount,
                    'spe_surcharge_rate' => (float) $this->fiscalHistory->spe_surcharge_rate,
                    'spe_surcharge_amount' => (float) $this->fiscalHistory->spe_surcharge_amount,
                    'exchange_rate' => (float) $this->fiscalHistory->exchange_rate,
                    'is_queued' => (bool) $this->fiscalHistory->is_queued,
                    'spe' => (bool) $this->fiscalHistory->spe,
                ];
            }),
        ];
    }
}
