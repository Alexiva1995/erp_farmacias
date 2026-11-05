<?php

namespace App\Http\Resources\Traceability;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TraceabilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $presentation = 1.0;
        $unitOfMeasure = '';
        if ($this->relationLoaded('product') && $this->product) {
            $presentation = (float) ($this->product->presentation ?? 1);
            if ($presentation <= 0) {
                $presentation = 1.0;
            }
            $unitOfMeasure = strtoupper(trim((string) ($this->product->unit_of_measure ?? '')));
        }

        $stockBefore = isset($this->global_stock_before) ? (float) $this->global_stock_before : (float) ($this->stock_before ?? 0);
        $stockAfter = isset($this->global_stock_after) ? (float) $this->global_stock_after : (float) ($this->stock_after ?? 0);
        $quantity = (float) $this->quantity;

        // Detección de insumos con presentación de gramaje / volumen
        $hasGrammage = $presentation > 1 && in_array($unitOfMeasure, ['G', 'GR', 'GRAMOS', 'GRAMO', 'ML', 'MILILITROS', 'CC', 'MG', 'KG', 'L', 'LT', 'LTS']);
        $baseQuantity = $hasGrammage ? round($quantity * $presentation, 2) : $quantity;
        $baseStockBefore = $hasGrammage ? round($stockBefore * $presentation, 2) : $stockBefore;
        $baseStockAfter = $hasGrammage ? round($stockAfter * $presentation, 2) : $stockAfter;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'movement_type' => $this->movement_type,
            'quantity' => $quantity,
            'stock_before' => (float) ($this->stock_before ?? 0),
            'stock_after' => (float) ($this->stock_after ?? 0),
            'global_stock_before' => $stockBefore,
            'global_stock_after' => $stockAfter,
            'has_grammage' => $hasGrammage,
            'base_quantity' => $baseQuantity,
            'base_stock_before' => $baseStockBefore,
            'base_stock_after' => $baseStockAfter,
            'unit_of_measure' => $unitOfMeasure ?: 'UND',
            'presentation_factor' => $presentation,
            'dish_id' => $this->dish_id,
            'is_recipe_consumption' => !is_null($this->dish_id),
            'movement_date' => $this->movement_date ? $this->movement_date->toIso8601String() : null,
            'order_id' => $this->order_id,
            'invoice_id' => $this->invoice_id,
            'product_lot_id' => $this->product_lot_id,

            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'photo_url' => $this->product->photo_url,
                    'psychotropic' => (bool) $this->product->psychotropic,
                    'iva' => (bool) $this->product->iva,
                    'is_colombian_origin' => (bool) $this->product->is_colombian_origin,
                    'active_ingredient' => $this->product->active_ingredient,
                    'presentation' => $this->product->presentation,
                    'unit_of_measure' => $this->product->unit_of_measure,
                    'laboratory' => $this->product->relationLoaded('laboratory') && $this->product->laboratory ? [
                        'id' => $this->product->laboratory->id,
                        'name' => $this->product->laboratory->name,
                    ] : null,
                ];
            }),

            'dish' => $this->whenLoaded('dish', function () {
                return $this->dish ? [
                    'id' => $this->dish->id,
                    'name' => $this->dish->name,
                ] : null;
            }),

            'user' => $this->whenLoaded('user', function () {
                return $this->user ? [
                    'id' => $this->user->id,
                    'username' => $this->user->username,
                    'email' => $this->user->email,
                    'employee' => $this->user->relationLoaded('employee') && $this->user->employee ? [
                        'name' => $this->user->employee->name,
                        'last_name' => $this->user->employee->last_name,
                    ] : null,
                ] : null;
            }),

            'invoice' => $this->whenLoaded('invoice', function () {
                return $this->invoice ? [
                    'id' => $this->invoice->id,
                    'invoice_number' => $this->invoice->invoice_number,
                    'supplier' => $this->invoice->relationLoaded('supplier') && $this->invoice->supplier ? [
                        'name' => $this->invoice->supplier->name,
                    ] : null,
                ] : null;
            }),
        ];
    }
}
