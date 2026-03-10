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
            'name' => $this->product_name,
            'category_name' => $this->category_name ?? 'N/A',
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
            // Info adicional
            'current_stock' => round($this->current_stock, 2),
            'last_cost' => round($this->last_cost, 2),
        ];
    }
}
