<?php

declare(strict_types=1);

namespace App\Http\Resources\Bi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventorySnapshotItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventory_snapshot_id' => $this->inventory_snapshot_id,
            'id_producto' => $this->product_id,
            'nombre_producto' => $this->product_name,
            'laboratorio' => $this->laboratory_name ?? 'N/A',
            'clasificacion_ventas' => $this->sales_class,
            'ventas_unidades_30d' => round((float) $this->sold_units_30d, 2),
            'ventas_totales_usd_30d' => round((float) $this->total_sales_usd_30d, 2),
            'stock_actual_unidades' => (int) round((float) $this->current_stock_units),
            'costo_unitario_usd' => round((float) $this->unit_cost_usd, 4),
            'precio_venta_usd' => round((float) $this->sale_price_usd, 4),
            'valor_inventario_usd' => round((float) $this->inventory_value_usd, 2),
            'margen_porcentaje' => round((float) $this->margin_percentage, 2),
            'cobertura_dias' => round((float) $this->coverage_days, 2),
            'gmroi_anual_porcentaje' => round((float) $this->gmroi_annual_percentage, 2),
            'dias_para_vencer' => $this->days_to_expiration,
            'es_sobrestock' => (bool) $this->is_overstock,
        ];
    }
}
