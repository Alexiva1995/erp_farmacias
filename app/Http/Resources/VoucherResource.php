<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
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
            'amount' => (float)$this->amount,
            'concept' => [
                'id' => $this->concept?->id,
                'name' => $this->concept?->name,
                'type' => $this->concept?->type,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
