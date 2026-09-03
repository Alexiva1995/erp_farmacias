<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpirationResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @return array<string, mixed>
    public function toArray(Request $request): array
    {
        $daysToExpire = $this->expiration_date ? (int) now()->diffInDays($this->expiration_date, false) : 0;
        $monthsRemaining = $this->expiration_date ? max(0, (float) now()->diffInMonths($this->expiration_date, false)) : 0;
        $salesAverage = (float) ($this->product?->sales_average ?? 0);
        $projectedSales = $salesAverage * $monthsRemaining;
        $overstockUnits = max(0, (float) $this->quantity - $projectedSales);
        $hasOverstockRisk = ($daysToExpire > 0 && $daysToExpire <= 120 && $overstockUnits > 0);
        $riskUnits = (int) ceil($overstockUnits);
        $riskLabel = $hasOverstockRisk ? "Sobrestock en Riesgo: {$riskUnits} " . ($riskUnits === 1 ? 'unidad' : 'unidades') : null;

        return [
            'id' => $this->id,
            'lot_number' => $this->lot_number,
            'expiration_date' => $this->expiration_date,
            'quantity' => (int) $this->quantity,
            'unit_cost' => $this->unit_cost,
            'product_id' => $this->product_id,
            'product' => $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'active_ingredient' => $this->product->active_ingredient,
                'barcode' => $this->product->barcode,
                'unit_cost' => $this->product->unit_cost,
                'sales_average' => $this->product->sales_average,
                'laboratory' => $this->product->laboratory ? [
                    'id' => $this->product->laboratory->id,
                    'name' => $this->product->laboratory->name,
                ] : null,
                'origin' => $this->product->origin ? [
                    'id' => $this->product->origin->id,
                    'name' => $this->product->origin->name,
                ] : null,
                'category' => $this->product->category ? [
                    'id' => $this->product->category->id,
                    'name' => $this->product->category->name,
                ] : null,
            ] : null,
            'days_to_expire' => $this->expiration_date ? now()->diffInDays($this->expiration_date, false) : null,
            'has_overstock_risk' => $hasOverstockRisk,
            'risk_units' => $hasOverstockRisk ? $riskUnits : 0,
            'risk_label' => $riskLabel,
            'risk_label_short' => $hasOverstockRisk ? "{$riskUnits} en riesgo" : null,
        ];
    }
}
