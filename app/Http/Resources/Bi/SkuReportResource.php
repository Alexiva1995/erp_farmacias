<?php

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
            'product_id' => $this->product_id,
            'barcode' => $this->barcode,
            'product_name' => $this->product_name,
            'laboratory_name' => $this->laboratory_name,
            'current_cost' => (float) $this->current_cost,
            'list_price' => (float) $this->list_price,
            'total_sold' => (int) $this->total_sold,
            
            // Calculados por SkuReportService
            'gross_margin_value' => (float) $this->gross_margin_value,
            'gross_margin_percent' => (float) $this->gross_margin_percent,
            
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
