<?php

namespace App\Http\Resources\Restaurant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiningReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_name' => $this->client_name,
            'client_whatsapp' => $this->client_whatsapp,
            'table_id' => $this->dining_table_id,
            'table_name' => $this->diningTable?->name,
            'zone_name' => $this->diningTable?->zone?->name,
            'date' => $this->date?->format('d/m/Y'),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'guests_count' => $this->guests_count,
            'special_requests' => $this->special_requests,
            'deposit_amount_usd' => (float) $this->deposit_amount_usd,
            'status' => $this->status,
        ];
    }
}
