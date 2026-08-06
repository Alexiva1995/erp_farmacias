<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutoReplenishmentConfigResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'is_active'           => (bool) $this->is_active,
            'tipo_filtracion'     => $this->tipo_filtracion,
            'lapso_de_tiempo'     => $this->lapso_de_tiempo,
            'min_solicitar'       => (float) $this->min_solicitar,
            'con_descuento'       => (bool) $this->con_descuento,
            'exclude_colombian'   => (bool) $this->exclude_colombian,
            'exclude_novaventa'   => (bool) $this->exclude_novaventa,
            'stock_filter'        => $this->stock_filter,
            'supplier_id'         => $this->supplier_id,
            'group_ids'           => $this->group_ids,
            'schedule_expression' => $this->schedule_expression,
            'last_run_at'         => $this->last_run_at ? $this->last_run_at->toIso8601String() : null,
            'last_run_products'   => $this->last_run_products,
            'last_run_orders'     => $this->last_run_orders,
            'created_at'          => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at'          => $this->updated_at ? $this->updated_at->toIso8601String() : null,
            
            // Relaciones
            'supplier'            => $this->whenLoaded('supplier', function () {
                return [
                    'id'   => $this->supplier->id,
                    'name' => $this->supplier->name,
                ];
            }),
        ];
    }
}
