<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Clase MarketOpportunityResource
 * 
 * Transforma el modelo de oportunidad de mercado en una estructura JSON estandarizada.
 */
class MarketOpportunityResource extends JsonResource
{
    /**
     * Transformar el recurso en un array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'supplier_id' => $this->supplier_id,
            'product_name_supplier' => $this->product_name_supplier,
            'product_name_inventory' => $this->product_name_inventory,
            'active_ingredient_inventory' => $this->active_ingredient_inventory ?? 'N/A',
            'laboratory_name' => $this->laboratory_name ?? 'N/A',
            'unit_cost_usd' => (float) $this->unit_cost_usd,
            'inventory_unit_cost' => (float) $this->inventory_unit_cost,
            'effective_min_cost' => (float) $this->effective_min_cost,
            'saving_percentage' => (float) $this->saving_percentage,
            'supplier_name' => $this->supplier_name,
            'total_sold_completed' => (float) $this->total_sold_completed,
            'lote_quantity' => (float) $this->lote_quantity,
            'promedio_calculado' => (float) $this->promedio_calculado,
            'solicitar' => (float) $this->solicitar,
            // Campo auxiliar para el frontend: pre-llenar con la sugerencia de pedido si es positiva
            'quantity_to_add' => $this->solicitar > 0 ? (int) $this->solicitar : null,
        ];
    }
}
