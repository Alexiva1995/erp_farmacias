<?php

namespace App\Http\Resources\Finances;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ClientResource;

class CreditResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $creditIds = [];
        if (is_array($this->credit_ids)) {
            $creditIds = array_map('intval', $this->credit_ids);
        } elseif (is_string($this->credit_ids) && !empty($this->credit_ids)) {
            $creditIds = array_map('intval', explode(',', $this->credit_ids));
        }

        return [
            'client_id' => $this->client_id,
            'total_pending_amount' => (float) $this->total_pending_amount,
            'credit_ids' => $creditIds,
            'status' => (int) $this->status,
            'credit_date' => $this->credit_date,
            'client' => new ClientResource($this->whenLoaded('client')),
        ];
    }
}
