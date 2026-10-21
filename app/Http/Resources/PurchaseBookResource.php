<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\GeneralSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseBookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = GeneralSetting::first();
        $enableZoomCurrent = (bool) ($settings?->enable_zoom ?? false);
        $enableZoomEffective = $this->isClosed()
            ? (bool) $this->enable_zoom_snapshot
            : $enableZoomCurrent;

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
            'enable_zoom' => $enableZoomEffective,
            'entries_count' => $this->whenCounted('entries', $this->entries_count),
            'total_purchases_with_vat' => (float) $this->total_purchases_with_vat,
            'total_exempt_purchases' => (float) $this->total_exempt_purchases,
            'total_general_taxable_base' => (float) $this->total_general_taxable_base,
            'total_general_vat' => (float) $this->total_general_vat,
            'total_reduced_taxable_base' => (float) $this->total_reduced_taxable_base,
            'total_reduced_vat' => (float) $this->total_reduced_vat,
            'total_vat_withheld' => (float) $this->total_vat_withheld,
            'closed_at' => $this->closed_at?->toISOString(),
            'closed_by' => $this->closedByUser ? [
                'id' => $this->closedByUser->id,
                'name' => $this->closedByUser->name,
            ] : null,
            'entries' => PurchaseBookEntryResource::collection($this->whenLoaded('entries')),
            'company_info' => [
                'name' => $settings?->app_name ?: 'EMPRESA',
                'rif' => $settings?->app_rif ?: $settings?->rif ?: 'J-00000000-0',
                'address' => $settings?->address ?: 'N/A',
                'taxpayer_type' => ($settings?->special_taxpayer_status === 'activada' || $settings?->enable_ce)
                    ? 'CONTRIBUYENTE ESPECIAL'
                    : 'CONTRIBUYENTE ORDINARIO',
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
