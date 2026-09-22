<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FiscalContributionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $today = now()->toDateString();
        $dueDateStr = $this->due_date ? $this->due_date->format('Y-m-d') : null;
        $isExpired = $this->status === 'pending' && $dueDateStr && $dueDateStr < $today;

        return [
            'id'                => $this->id,
            'period'            => $this->period,
            'tax_type'          => $this->tax_type,
            'document_number'   => $this->document_number,
            'operation_date'    => $this->operation_date ? $this->operation_date->format('Y-m-d') : null,
            'due_date'          => $dueDateStr,
            'amount'            => (float)$this->amount,
            'status'            => $this->status,
            'is_expired'        => $isExpired,
            'payment_date'      => $this->payment_date ? $this->payment_date->format('Y-m-d') : null,
            'payment_reference' => $this->payment_reference,
            'source'            => $this->source,
            'notes'             => $this->notes,
            'created_by_user'   => $this->relationLoaded('creator') && $this->creator ? [
                'id'       => $this->creator->id,
                'username' => $this->creator->username,
            ] : null,
            'created_at'        => $this->created_at ? $this->created_at->format('Y-m-d H:i') : null,
        ];
    }
}
