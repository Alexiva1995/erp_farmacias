<?php

declare(strict_types=1);

namespace App\Http\Resources\Returns;

use App\Models\ReturnEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnOrderResource extends JsonResource
{
    /**
     * Transformar la orden elegible para devolución.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = strtoupper((string) ($this->currency ?? 'USD'));
        $returnsByProduct = $this->relationLoaded('returns')
            ? $this->returns
                ->filter(fn ($r) => $r->status === null || $r->status === ReturnEntry::APPROVED)
                ->groupBy('product_id')
                ->map(fn ($group) => (int) $group->sum('quantity'))
            : collect();

        return [
            'id' => $this->id,
            'order_date' => $this->order_date?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'currency' => $currency,
            'usd_conversion' => (float) ($this->usd_conversion ?? 1),
            'total_amount' => (float) $this->total_amount,
            'total_amount_usd' => (float) ($this->total_amount_usd ?? $this->total_amount),
            'client' => $this->relationLoaded('client') && $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
                'last_name' => $this->client->last_name,
                'identification_type' => $this->client->identification_type,
                'identification' => $this->client->identification,
            ] : null,
            'fiscal_history' => $this->relationLoaded('fiscalHistory') && $this->fiscalHistory ? [
                'id' => $this->fiscalHistory->id,
                'invoice_number' => $this->fiscalHistory->invoice_number,
                'fiscal_id' => $this->fiscalHistory->fiscal_id,
                'invoice_date' => $this->fiscalHistory->invoice_date,
                'exchange_rate' => (float) ($this->fiscalHistory->exchange_rate ?? 1),
            ] : null,
            'details' => $this->relationLoaded('details') ? $this->details->map(function ($detail) use ($currency, $returnsByProduct) {
                $soldQty = (int) $detail->quantity;
                $alreadyReturned = (int) ($returnsByProduct[$detail->product_id] ?? 0);
                $availableQty = max(0, $soldQty - $alreadyReturned);

                return [
                    'id' => $detail->id,
                    'product_id' => $detail->product_id,
                    'product_lot_id' => $detail->product_lot_id,
                    'quantity' => $soldQty,
                    'already_returned' => $alreadyReturned,
                    'available_quantity' => $availableQty,
                    'price' => (float) $detail->price,
                    'unit_price_usd' => (float) ($detail->unit_price_usd ?? 0),
                    'discount_percentage' => (float) ($detail->discount_percentage ?? 0),
                    'currency' => $currency,
                    'product' => $detail->relationLoaded('product') && $detail->product ? [
                        'id' => $detail->product->id,
                        'name' => $detail->product->name,
                        'active_ingredient' => $detail->product->active_ingredient,
                        'presentation' => $detail->product->presentation,
                        'iva' => (bool) ($detail->product->iva ?? false),
                        'laboratory' => $detail->product->relationLoaded('laboratory') && $detail->product->laboratory ? [
                            'id' => $detail->product->laboratory->id,
                            'name' => $detail->product->laboratory->name,
                        ] : null,
                    ] : null,
                ];
            })->values()->all() : [],
        ];
    }
}
