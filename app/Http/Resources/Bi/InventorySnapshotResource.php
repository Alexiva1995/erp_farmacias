<?php

declare(strict_types=1);

namespace App\Http\Resources\Bi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventorySnapshotResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'cutoff_date' => $this->cutoff_date ? $this->cutoff_date->format('Y-m-d') : null,
            'period_days' => (int) $this->period_days,
            'total_products' => (int) $this->total_products,
            'total_inventory_units' => round((float) $this->total_inventory_units, 2),
            'total_inventory_value' => round((float) $this->total_inventory_value, 2),
            'total_sales_units' => round((float) $this->total_sales_units, 2),
            'total_sales_value' => round((float) $this->total_sales_value, 2),
            'overstock_products_count' => (int) $this->overstock_products_count,
            'overstock_inventory_value' => round((float) $this->overstock_inventory_value, 2),
            'is_automatic' => (bool) $this->is_automatic,
            'created_by_user_id' => $this->created_by_user_id,
            'creator_name' => $this->creator ? ($this->creator->username ?? $this->creator->name) : null,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
