<?php

declare(strict_types=1);

namespace App\Http\Resources\Bi;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Formatea un lote individual dentro del reporte de devoluciones.
 * Garantiza tipos estrictos y evita serializar columnas innecesarias.
 */
class SupplierReturnsLotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'lot_id'           => (int) $this['lot_id'],
            'product_id'       => (int) $this['product_id'],
            'product_name'     => (string) ($this['product_name'] ?? ''),
            'barcode'          => $this['barcode'] ?? null,
            'active_ingredient'=> $this['active_ingredient'] ?? null,
            'presentation'     => $this['presentation'] ?? null,
            'laboratory_id'    => $this['laboratory_id'] ? (int) $this['laboratory_id'] : null,
            'laboratory_name'  => $this['laboratory_name'] ?? 'SIN LABORATORIO',
            'supplier_id'      => $this['supplier_id'] ? (int) $this['supplier_id'] : null,
            'supplier_name'    => $this['supplier_name'] ?? null,
            'lot_number'       => (string) ($this['lot_number'] ?? ''),
            'expiration_date'  => $this['expiration_date'],
            'days_to_expiry'   => (int) ($this['days_to_expiry'] ?? 0),
            'quantity'         => (float) ($this['quantity'] ?? 0),
            'unit_cost'        => (float) ($this['unit_cost'] ?? 0),
            'total_amount'     => (float) ($this['total_amount'] ?? 0),
            'purchase_date'    => $this['purchase_date'],
        ];
    }
}
