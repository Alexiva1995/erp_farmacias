<?php

namespace App\Http\Resources\Restaurant;

use App\Enums\KitchenWasteReasonEnum;
use App\Enums\KitchenWasteStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KitchenWasteResource extends JsonResource
{
    /**
     * Transforma el registro de merma en un arreglo estandarizado para la API.
     */
    public function toArray(Request $request): array
    {
        $statusValue = is_object($this->status) ? $this->status->value : ($this->status ?: KitchenWasteStatusEnum::PENDING->value);
        $statusEnum = KitchenWasteStatusEnum::tryFrom((string) $statusValue) ?? KitchenWasteStatusEnum::PENDING;

        $reasonValue = is_object($this->reason) ? $this->reason->value : (string) $this->reason;
        $reasonEnum = KitchenWasteReasonEnum::tryFrom($reasonValue) ?? KitchenWasteReasonEnum::BURNED_SPOILED;

        $photoUrl = null;
        if (!empty($this->photo_path)) {
            $photoUrl = str_starts_with($this->photo_path, 'http')
                ? $this->photo_path
                : asset('storage/' . ltrim($this->photo_path, '/'));
        }

        // Costo unificado desde la tabla products (para mostrar siempre trazabilidad contra costo unificado)
        $unifiedProductCost = (float) ($this->product?->unit_cost ?? $this->unit_cost ?? 0);
        $ingredientsBreakdown = [];

        if ($this->item_type === 'dish' && $this->dish) {
            if ($this->dish->relationLoaded('ingredients') && $this->dish->ingredients->isNotEmpty()) {
                $calcDishCost = 0.0;
                foreach ($this->dish->ingredients as $ing) {
                    $ingUnifiedCost = (float) ($ing->unit_cost ?? 0);
                    $portion = (float) ($ing->pivot->portion ?? 0);
                    $lineTotalQty = round($portion * (float) $this->quantity, 4);
                    $lineCost = round($ingUnifiedCost * $portion, 4);
                    $calcDishCost += $lineCost;

                    $ingredientsBreakdown[] = [
                        'product_id' => $ing->id,
                        'name' => $ing->name,
                        'portion_per_unit' => $portion,
                        'total_deducted_qty' => $lineTotalQty,
                        'unit_of_measure' => $ing->unit_of_measure ?? 'und',
                        'unified_unit_cost' => $ingUnifiedCost,
                    ];
                }
                $yieldQty = (float) ($this->dish->expected_yield_quantity ?: 1.0);
                if ((int) $this->dish->status === 3 && $yieldQty > 0) {
                    $calcDishCost = $calcDishCost / $yieldQty;
                }
                if ($calcDishCost > 0) {
                    $unifiedProductCost = round($calcDishCost, 4);
                }
            }
        }

        $isSubRecipe = $this->item_type === 'dish' && (int) ($this->dish?->status ?? 0) === 3;

        return [
            'id' => $this->id,
            'item_type' => $this->item_type,
            'item_type_label' => $this->item_type === 'product'
                ? 'Insumo / Producto'
                : ($isSubRecipe ? 'Sub-receta (Prep)' : 'Platillo Terminado'),
            'product_id' => $this->product_id,
            'dish_id' => $this->dish_id,
            'lot_id' => $this->lot_id,
            'lot_number' => $this->lot?->lot_number,
            'item_name' => $this->item_type === 'product'
                ? ($this->product?->name ?? "Producto #{$this->product_id}")
                : ($this->dish?->name ?? "Platillo #{$this->dish_id}"),
            'product_unit' => $this->item_type === 'product'
                ? ($this->product?->unit_of_measure ?? 'und')
                : ($isSubRecipe ? 'lote/porción' : 'plato'),
            'current_stock' => (float) ($this->product?->stock ?? 0),
            'quantity' => (float) $this->quantity,
            'unit_cost' => (float) $this->unit_cost,
            'unified_unit_cost' => round($unifiedProductCost, 4),
            'total_loss_usd' => (float) $this->total_loss_usd,
            'reason' => $reasonEnum->value,
            'reason_label' => $reasonEnum->label(),
            'is_operational_waste' => $reasonEnum->isOperationalWaste(),
            'reason_category_label' => $reasonEnum->categoryLabel(),
            'requires_photo' => $reasonEnum->requiresPhoto(),
            'kitchen_station_id' => $this->kitchen_station_id,
            'station_name' => $this->kitchenStation?->name ?? 'Cocina General',
            'ingredients_breakdown' => $ingredientsBreakdown,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name ?? $this->user?->username ?? 'Usuario',
            'notes' => $this->notes,
            'status' => $statusEnum->value,
            'status_label' => $statusEnum->label(),
            'status_color' => $statusEnum->color(),
            'photo_path' => $this->photo_path,
            'photo_url' => $photoUrl,
            'approved_by' => $this->approved_by,
            'approved_by_name' => $this->approver?->name ?? $this->approver?->username,
            'approved_at' => $this->approved_at?->format('d/m/Y'),
            'rejection_reason' => $this->rejection_reason,
            'occurred_at' => $this->occurred_at?->format('d/m/Y') ?? $this->created_at?->format('d/m/Y'),
        ];
    }
}
