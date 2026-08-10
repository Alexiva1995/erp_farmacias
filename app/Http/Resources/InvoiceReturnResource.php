<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceReturnResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->invoice?->invoice_number ?? 'S/N',
            'supplier_name' => $this->invoice?->supplier?->name ?? 'Proveedor Desconocido',
            'supplier_rif' => $this->invoice?->supplier?->rif ?? null,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name ?? 'Producto',
            'barcode' => $this->product?->barcode ?? '',
            'sku' => $this->product?->barcode ?? '',
            'quantity' => (float) $this->quantity,
            'amount_refunded' => (float) $this->amount_refunded,
            'supplier_discount_percentage' => (float) ($this->supplier_discount_percentage ?? 0),
            'return_date' => $this->return_date ? ($this->return_date instanceof \Carbon\Carbon ? $this->return_date->format('Y-m-d') : \Carbon\Carbon::parse($this->return_date)->format('Y-m-d')) : null,
            'lot_number' => $this->lot_number,
            'expiration_date' => $this->expiration_date ? ($this->expiration_date instanceof \Carbon\Carbon ? $this->expiration_date->format('Y-m-d') : \Carbon\Carbon::parse($this->expiration_date)->format('Y-m-d')) : null,
            'status' => $this->status ? $this->status->value : 'pending',
            'status_label' => $this->status ? $this->status->label() : 'Pendiente',
            'created_at' => $this->created_at ? ($this->created_at instanceof \Carbon\Carbon ? $this->created_at->toDateTimeString() : \Carbon\Carbon::parse($this->created_at)->toDateTimeString()) : null,
        ];
    }
}
