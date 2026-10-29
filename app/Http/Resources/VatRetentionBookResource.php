<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VatRetentionBookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period_type' => $this->period_type?->value ?? 'fortnightly',
            'year' => $this->year,
            'month' => $this->month,
            'fortnight' => $this->fortnight,
            'period_label' => $this->fortnight
                ? "Quincena {$this->fortnight} - " . str_pad((string)$this->month, 2, '0', STR_PAD_LEFT) . "/{$this->year}"
                : "Mes " . str_pad((string)$this->month, 2, '0', STR_PAD_LEFT) . "/{$this->year}",
            'start_date' => $this->start_date ? $this->start_date->format('Y-m-d') : null,
            'end_date' => $this->end_date ? $this->end_date->format('Y-m-d') : null,
            'status' => $this->status?->value ?? 'open',
            'is_open' => $this->isOpen(),
            'is_closed' => $this->isClosed(),
            'total_documents' => (int) $this->total_documents,
            'total_document_amount' => (float) $this->total_document_amount,
            'total_taxable_base' => (float) $this->total_taxable_base,
            'total_tax_amount' => (float) $this->total_tax_amount,
            'total_withheld_amount' => (float) $this->total_withheld_amount,
            'total_exempt_amount' => (float) $this->total_exempt_amount,
            'closed_at' => $this->closed_at?->toISOString(),
            'closed_by' => $this->closedByUser ? [
                'id' => $this->closedByUser->id,
                'name' => $this->closedByUser->name,
            ] : null,
            'entries' => $this->relationLoaded('entries')
                ? VatRetentionBookEntryResource::collection($this->entries)
                : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
