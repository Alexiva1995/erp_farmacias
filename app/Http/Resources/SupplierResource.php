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
            'address' => $this->address,
            'email' => $this->email,
            'status' => $this->status,
            'public_token' => $this->public_token,
            'dispatch_days' => $this->dispatch_days,
            'social_reason' => $this->social_reason,
            'rif' => $this->rif,
            'credit_days' => $this->credit_days,
            'order_days' => $this->order_days,
            'payment_method' => $this->payment_method,
            'cash_payment' => $this->cash_payment,
            'charges_igtf' => $this->charges_igtf,
            'payment_due_type' => $this->payment_due_type,
            'custom_due_days' => $this->custom_due_days,
            'payment_due_reference' => $this->payment_due_reference,
            'invoice_date_reference' => $this->invoice_date_reference,
            'debt' => $this->debt,
            'latest_score_value' => $this->latest_score_value,
            'is_indexed' => (bool) $this->is_indexed,
            'is_active' => (bool) ($this->is_active ?? true),
            'type' => $this->type,
            'score_breakdown' => $this->score_breakdown,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relaciones
            'latest_score' => $this->whenLoaded('latestScore'),
            'payment_rules' => $this->whenLoaded('paymentRules'),
            'payment_date' => $this->whenLoaded('paymentDate'),
        ];
    }
}
