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
            'order_date' => $this->order_date,
            'total_amount' => $this->total_amount,
            'currency' => $this->currency,
            'status' => $this->status,
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
        ];
    }
}
