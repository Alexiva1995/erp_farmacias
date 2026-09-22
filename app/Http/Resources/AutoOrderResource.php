<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AutoOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $transmissionType = null;
        $supplier = $this->relationLoaded('supplier') ? $this->supplier : ($this->supplier_id ? \App\Models\Supplier::with('connections')->find($this->supplier_id) : null);
        
        if ($supplier) {
            $supplierName = strtoupper($supplier->name ?? '');
            $connections = $supplier->relationLoaded('connections') ? $supplier->connections : $supplier->connections()->get();

            $hasFtp = str_contains($supplierName, 'NENA') || str_contains($supplierName, 'DRONENA') ||
                      str_contains($supplierName, 'VITALCLINIC') || str_contains($supplierName, 'VITAL CLINIC') ||
                      str_contains($supplierName, 'DROCERCA') || str_contains($supplierName, 'CERCA') ||
                      $connections->contains(fn($c) => in_array($c->type, ['ftp', 'sftp', 'drocerca_bot']) || str_contains($c->host ?? '', 'dronena') || str_contains($c->host ?? '', 'vitalclinic') || str_contains($c->host ?? '', 'drocerca'));

            $hasApi = str_contains($supplierName, 'MAFARTA') || str_contains($supplierName, 'COBECA') || (int)$supplier->id === 23 || (int)$supplier->id === 1011 ||
                      str_contains($supplierName, 'CRIST') || str_contains($supplierName, 'CRISTALMEDICALS') || (int)$supplier->id === 1002 ||
                      $connections->contains(fn($c) => in_array($c->type, ['api', 'rest', 'soap']) || str_contains($c->host ?? '', 'cristmedicals'));

            $hasEmail = !empty($supplier->payment_email) || !empty($supplier->email);

            if ($hasFtp) {
                $transmissionType = 'ftp';
            } elseif ($hasApi) {
                $transmissionType = 'api';
            } elseif ($hasEmail) {
                $transmissionType = 'email';
            }
        }

        return [
            'id'                      => $this->id,
            'supplier_id'             => $this->supplier_id ?? null,
            'supplier_name'           => $this->supplier_name ?? ($this->relationLoaded('supplier') ? $this->supplier?->name : null),
            'phone'                   => $this->phone ?? ($this->relationLoaded('supplier') ? $this->supplier?->sales_phone : null),
            'status'                  => is_object($this->status) ? $this->status->value : (int) $this->status,
            'transmission_type'       => $transmissionType,
            'total_quantity'          => (float) ($this->total_quantity ?? 0),
            'total_amount'            => (float) ($this->total_amount ?? 0),
            'order_date'              => $this->order_date ? (is_string($this->order_date) ? $this->order_date : $this->order_date->toDateTimeString()) : null,
            'tentative_delivery_date' => $this->tentative_delivery_date ? (is_string($this->tentative_delivery_date) ? $this->tentative_delivery_date : $this->tentative_delivery_date->toDateString()) : null,
            'hash_token'              => $this->hash_token ?? null,
            'created_at'              => $this->created_at ? $this->created_at->toDateTimeString() : null,
        ];
    }
}
