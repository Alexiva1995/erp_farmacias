<?php

namespace App\Http\Resources\Finances;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_name' => $this->user ? $this->user->name : ($this->user_name ?? 'Sistema'),
            'description' => $this->description,
            'type' => $this->type ?? $this->movement_type ?? 'OUT',
            'movement_type' => $this->movement_type ?? $this->type ?? 'OUT',
            'amount' => (float)$this->amount,
            'balance' => (float)($this->balance ?? $this->running_balance ?? $this->current_balance ?? 0),
            'category_name' => $this->category ? $this->category->name : ($this->category_name ?? 'N/A'),
            'transaction_date' => $this->transaction_date 
                ? (\Carbon\Carbon::parse($this->transaction_date)->toDateTimeString()) 
                : ($this->created_at ? \Carbon\Carbon::parse($this->created_at)->toDateTimeString() : null),
            'currency' => $this->currency,
            'exchange_rate' => $this->exchange_rate !== null ? (float)$this->exchange_rate : 1.0,
        ];
    }
}
