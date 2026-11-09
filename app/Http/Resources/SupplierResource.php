<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
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
            'name' => $this->name,
            'sales_phone' => $this->sales_phone,
            'collections_phone' => $this->collections_phone,
            'payment_email' => $this->payment_email,
            'address' => $this->address,
            'email' => $this->email,
            'status' => $this->status,
            'public_token' => $this->public_token,
            'dispatch_days' => $this->dispatch_days,
            'social_reason' => $this->social_reason,
            'rif' => $this->rif,
            'is_fiscal' => (bool) ($this->is_fiscal ?? true),
            'credit_days' => $this->credit_days,
            'min_order_amount' => (float) ($this->min_order_amount ?? 0),
            'order_days' => $this->order_days,
            'payment_method' => $this->payment_method,
            'cash_payment' => $this->cash_payment,
            'charges_igtf' => $this->charges_igtf,
            'payment_due_type' => $this->payment_due_type,
            'custom_due_days' => $this->custom_due_days,
            'payment_due_reference' => $this->payment_due_reference,
            'invoice_date_reference' => $this->invoice_date_reference,
            'debt' => (request()->header('X-Master-Catalog-Context') === '1' || request()->boolean('is_master')) ? 0 : $this->debt,
            'primary_connection_type' => $this->relationLoaded('connections') ? ($this->connections->first(fn($c) => in_array($c->type, ['api', 'http']))?->type ?? $this->connections->first(fn($c) => in_array($c->type, ['ftp', 'sftp']))?->type ?? $this->connections->first(fn($c) => in_array($c->type, ['email', 'file']))?->type ?? $this->connections->first()?->type ?? 'file') : null,
            'has_structure' => $this->relationLoaded('connections') ? $this->connections->contains(fn($c) => !empty($c->structure)) : false,
            'structure_summary' => $this->relationLoaded('connections') ? ($this->connections->first(fn($c) => !empty($c->structure))?->structure ?? null) : null,
            'latest_score_value' => $this->latest_score_value,
            'is_indexed' => (bool) $this->is_indexed,
            'is_active' => (request()->header('X-Master-Catalog-Context') === '1' || request()->boolean('is_master')) ? true : (bool) ($this->is_active ?? true),
            'type' => $this->type instanceof \App\Enums\SupplierType ? $this->type->value : ($this->type ?? 'drogueria'),
            'business_types' => is_string($this->business_types) ? (json_decode($this->business_types, true) ?: []) : ($this->business_types ?? []),
            'score_breakdown' => $this->score_breakdown,
            'last_sync_at' => $this->connection?->last_connection ?? ($this->relationLoaded('connections') ? $this->connections->max('last_connection') : null),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relaciones
            'latest_score' => $this->whenLoaded('latestScore'),
            'payment_rules' => $this->whenLoaded('paymentRules'),
            'payment_date' => $this->whenLoaded('paymentDate'),
        ];
    }
}
