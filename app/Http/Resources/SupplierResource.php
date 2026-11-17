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
        $isMaster = $request->header('X-Master-Catalog-Context') === '1' || $request->boolean('is_master');

        $syncConnections = $this->relationLoaded('connections')
            ? $this->connections->filter(fn ($c) => !str_contains((string) ($c->type ?? ''), 'bot'))
            : collect();

        $primaryConn = $syncConnections->first(fn ($c) => in_array($c->type, ['api', 'http']))
            ?? $syncConnections->first(fn ($c) => in_array($c->type, ['ftp', 'sftp']))
            ?? $syncConnections->first(fn ($c) => in_array($c->type, ['email', 'file']))
            ?? $syncConnections->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'logo_url' => $this->logo_url ?? $this->master_logo_url ?? null,
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
            'comparator_discount_mode' => $this->comparator_discount_mode ?? 'original',
            'comparator_discount_percentage' => (float) ($this->comparator_discount_percentage ?? 0),
            'order_days' => $this->order_days,
            'payment_method' => $this->payment_method,
            'is_indexed' => (bool) ($this->is_indexed ?? false),
            'max_early_payment_discount' => $this->relationLoaded('paymentRules')
                ? (float) ($this->paymentRules->max('discount_percentage') ?? 0)
                : 0.0,
            'pending_orders_count' => (int) ($this->pending_orders_count ?? 0),
            'sent_orders_count' => (int) ($this->sent_orders_count ?? 0),
            'sent_order_date' => $this->sent_order_date ?? null,
            'is_order_overdue' => (bool) ($this->is_order_overdue ?? false),
            'unpaid_invoices_count' => (int) ($this->unpaid_invoices_count ?? 0),
            'overdue_invoices_count' => (int) ($this->overdue_invoices_count ?? 0),
            'overdue_debt' => $isMaster ? 0 : (float) ($this->overdue_debt ?? 0),
            'cash_payment' => $this->cash_payment,
            'charges_igtf' => $this->charges_igtf,
            'payment_due_type' => $this->payment_due_type,
            'custom_due_days' => $this->custom_due_days,
            'payment_due_reference' => $this->payment_due_reference,
            'invoice_date_reference' => $this->invoice_date_reference,
            'debt' => $isMaster ? 0 : $this->debt,
            'primary_connection_type' => $this->relationLoaded('connections') ? ($primaryConn?->type ?? 'file') : null,
            'allowed_connection_types' => $this->master_allowed_connection_types ?? (
                $syncConnections->isNotEmpty()
                    ? $syncConnections->pluck('type')->filter()->unique()->values()->all()
                    : null
            ),
            'has_structure' => $syncConnections->contains(fn ($c) => !empty($c->structure)),
            'structure_summary' => $syncConnections->first(fn ($c) => !empty($c->structure))?->structure ?? null,
            'latest_score_value' => $this->latest_score_value,
            'products_count' => (int) ($this->product_suppliers_count ?? 0),
            'latest_sync_status' => $this->relationLoaded('latestConnectionStatus') && $this->latestConnectionStatus ? [
                'status' => $this->latestConnectionStatus->status,
                'method' => $this->latestConnectionStatus->method,
                'message' => $this->latestConnectionStatus->message,
                'count_product' => (int) ($this->latestConnectionStatus->count_product ?? 0),
                'count_invoice' => (int) ($this->latestConnectionStatus->count_invoice ?? 0),
                'created_at' => $this->latestConnectionStatus->created_at,
            ] : null,
            'is_indexed' => (bool) $this->is_indexed,
            'is_active' => $isMaster ? true : (bool) ($this->is_active ?? true),
            'type' => $this->type instanceof \App\Enums\SupplierType ? $this->type->value : ($this->type ?? 'drogueria'),
            'business_types' => is_string($this->business_types) ? (json_decode($this->business_types, true) ?: []) : ($this->business_types ?? []),
            'score_breakdown' => $this->score_breakdown,
            'last_sync_at' => $syncConnections->max('last_connection'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relaciones
            'latest_score' => $this->whenLoaded('latestScore'),
            'payment_rules' => $this->whenLoaded('paymentRules'),
            'payment_date' => $this->whenLoaded('paymentDate'),
        ];
    }
}
