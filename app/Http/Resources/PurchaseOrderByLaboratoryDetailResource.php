<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderByLaboratoryDetailResource extends JsonResource
{
    /**
     * Transforma el detalle de orden en un arreglo.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $order = $this->order ?? $this->autoOrder;
        $supplier = $order?->supplier ?? $this->productSupplier?->supplier;

        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'order_status' => $order?->status?->value ?? (int) ($order?->status ?? 0),
            'order_created_at' => $order?->created_at?->format('Y-m-d H:i:s'),
            'product_id' => $this->product_id,
            'product_name' => $product?->name ?? 'Producto Desconocido',
            'product_barcode' => $product?->barcode ?? null,
            'laboratory_id' => $product?->laboratory_id,
            'laboratory_name' => $product?->laboratory?->name ?? 'Sin Laboratorio',
            'supplier_id' => $supplier?->id ?? null,
            'supplier_name' => $supplier?->name ?? 'Proveedor Desconocido',
            'quantity' => (int) $this->quantity,
            'unit_cost' => (float) $this->unit_cost,
            'subtotal' => (float) $this->subtotal,
            'status' => (int) ($this->status ?? 0),
            'received' => (int) ($this->received ?? 0),
            'supplier_confirmed' => $this->supplier_confirmed,
            'supplier_rejected_reason' => $this->supplier_rejected_reason,
        ];
    }
}
