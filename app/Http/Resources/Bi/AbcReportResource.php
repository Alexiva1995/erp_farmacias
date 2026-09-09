<?php

namespace App\Http\Resources\Bi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Clase AbcReportResource
 * 
 * Formatea un ítem del reporte ABC Multicriterio en una respuesta JSON ordenada.
 */
class AbcReportResource extends JsonResource
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
            'item_type' => $this->item_type ?? 'product',
            'name' => $this->product_name,
            'laboratory_name' => $this->laboratory_name ?? 'N/A',
            'sold_units' => round($this->sold_units, 2),
            'total_sales' => round($this->total_sales, 2),
            'total_cost' => round($this->total_cost, 2),
            'margin_amount' => round($this->margin_amount, 2),
            'margin_percentage' => round($this->margin_percentage, 2),
            'inventory_days' => round($this->inventory_days, 1),
            // Clasificaciones XYZ - ABC
            'class_sales' => $this->class_sales,
            'class_margin' => $this->class_margin,
            'class_rotation' => $this->class_rotation,
            'final_classification' => $this->final_classification,
            'contribution_sales_pct' => round($this->contribution_sales_pct ?? 0, 4),
            'contribution_margin_pct' => round($this->contribution_margin_pct ?? 0, 4),
            // Info adicional
            'current_stock' => round($this->current_stock, 2),
            'last_cost' => round($this->last_cost, 2),
            'sale_price' => isset($this->sale_price) ? round((float)$this->sale_price, 2) : null,
            'gmroi' => round($this->gmroi, 2),
            'inventory_value' => round($this->inventory_value, 2),
            'sales_average' => round($this->sales_average ?? 0, 2),
            'last_sale_date' => $this->last_sale_date,
            'next_expiration_date' => $this->next_expiration_date,
            'days_to_expiration' => $this->days_to_expiration,
            'months_to_expiration' => $this->months_to_expiration,
            'is_expiring_soon' => $this->is_expiring_soon ?? false,
            'has_expiration_risk' => $this->has_expiration_risk ?? false,
            'risk_lot_date' => $this->risk_lot_date,
            'risk_expiring_units' => round($this->risk_expiring_units ?? 0, 1),
            'risk_expiring_capital' => round($this->risk_expiring_capital ?? 0, 2),
            'individual_offer_discount' => $this->individual_offer_discount,
            'has_individual_offer' => $this->has_individual_offer ?? false,
        ];
    }
}
