<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'court_id' => $this->court_id,
            'court_name' => $this->court ? $this->court->name : null,
            'court_price' => $this->court ? $this->court->price : null,
            'date' => $this->date ? $this->date->format('Y-m-d') : null,
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'client_name' => $this->client_name,
            'client_whatsapp' => $this->client_whatsapp,
            'status' => $this->status,
            'request_weekly_fixed' => (bool)$this->request_weekly_fixed,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
