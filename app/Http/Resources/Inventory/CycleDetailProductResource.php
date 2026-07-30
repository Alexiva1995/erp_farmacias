<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CycleDetailProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = is_array($this->resource) ? (object) $this->resource : $this->resource;

        return [
            'id' => $item->id ?? null,
            'source_type' => $item->source_type ?? 'product_count',
            'product_id' => (int) ($item->product_id ?? 0),
            'system_quantity' => (float) ($item->system_quantity ?? 0),
            'final_quantity' => (float) ($item->final_quantity ?? $item->counted_quantity ?? 0),
            'counted_quantity' => (float) ($item->counted_quantity ?? 0),
            'discrepancy' => (float) ($item->discrepancy ?? 0),
            'created_at' => $item->created_at ?? null,
            'product' => isset($item->product) ? [
                'id' => $item->product['id'] ?? $item->product->id ?? null,
                'name' => $item->product['name'] ?? $item->product->name ?? '',
                'photo_url' => $item->product['photo_url'] ?? $item->product->photo_url ?? null,
                'unit_cost' => (float) ($item->product['unit_cost'] ?? $item->product->unit_cost ?? 0),
                'sale_price' => (float) ($item->product['sale_price'] ?? $item->product->sale_price ?? 0),
                'laboratory' => isset($item->product['laboratory']) ? [
                    'name' => $item->product['laboratory']['name'] ?? $item->product->laboratory->name ?? ''
                ] : null
            ] : null,
            'user' => isset($item->user) ? [
                'email' => $item->user['email'] ?? $item->user->email ?? '',
                'employee_name' => $item->user['employee_name'] ?? $item->user->employee_name ?? '',
                'employee_last_name' => $item->user['employee_last_name'] ?? $item->user->employee_last_name ?? '',
            ] : null,
            'supervisor' => isset($item->supervisor) ? [
                'employee_name' => $item->supervisor['employee_name'] ?? $item->supervisor->employee_name ?? '',
                'employee_last_name' => $item->supervisor['employee_last_name'] ?? $item->supervisor->employee_last_name ?? '',
            ] : null,
        ];
    }
}
