<?php

declare(strict_types=1);

namespace App\Http\Resources\Bi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkuReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->product_id,
            'product_id' => $this->product_id,
            'barcode' => $this->barcode,
            'product_name' => $this->product_name,
            'active_ingredient' => $this->active_ingredient,
            'laboratory_name' => $this->laboratory_name,
            'current_stock' => (float) ($this->current_stock ?? 0),
            'current_cost' => (float) $this->current_cost,
            'list_price' => (float) $this->list_price,
            'total_sold' => (int) $this->total_sold,
            'total_revenue' => (float) ($this->total_revenue ?? 0),
            
            // Métricas financieras calculadas por SkuReportService
            'gross_margin_value' => (float) $this->gross_margin_value,
            'gross_margin_percent' => (float) $this->gross_margin_percent,
            
            'total_discount_amount' => (float) ($this->total_discount_amount ?? 0),
            'discount_avg_percent' => (float) $this->discount_avg_percent,
            
            'net_margin_value' => (float) $this->net_margin_value,
            'net_margin_percent' => (float) $this->net_margin_percent,
            
            'loss_value' => (float) $this->loss_value,
            
            'real_margin_value' => (float) $this->real_margin_value,
            'real_margin_percent' => (float) $this->real_margin_percent,
            
            'semaphore' => $this->semaphore,
        ];
    }
}
