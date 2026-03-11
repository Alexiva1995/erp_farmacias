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
            'effective_min_cost' => (float) $this->effective_min_cost,
            'saving_amount' => (float) $this->saving_amount,
            'saving_percentage' => (float) $this->saving_percentage,
            // Campo auxiliar para el frontend
            'quantity_to_add' => 1,
        ];
    }
}
