<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
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
            'name' => $this->name,
            'sales_phone' => $this->sales_phone,
            'collections_phone' => $this->collections_phone,
            'address' => $this->address,
            'email' => $this->email,
            'status' => $this->status,
            'public_token' => $this->public_token,
            'dispatch_days' => $this->dispatch_days,
            'debt' => $this->debt,
            'latest_score_value' => $this->latest_score_value,
            'score_breakdown' => $this->score_breakdown,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relaciones
            'latest_score' => $this->whenLoaded('latestScore'),
            'payment_rules' => $this->whenLoaded('paymentRules'),
            'payment_date' => $this->whenLoaded('paymentDate'),
        ];
    }
}
