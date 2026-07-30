<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CycleSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'cycle_id' => (int) $this->cycle_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'cycle_status' => $this->cycle_status,
            'total_products' => (int) ($this->total_products ?? 0),
            'total_surplus' => (float) ($this->total_surplus ?? 0),
            'total_shortage' => (float) ($this->total_shortage ?? 0),
            'net_total' => (float) ($this->net_total ?? 0),
        ];
    }
}
