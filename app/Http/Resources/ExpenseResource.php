<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
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
            'invoice_number' => $this->invoice_number,
            'control_number' => $this->control_number,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'count' => $this->count,
            'payment_method' => $this->count,
            'expense_date' => $this->expense_date ? (is_string($this->expense_date) ? substr($this->expense_date, 0, 10) : $this->expense_date->format('Y-m-d')) : null,
            'has_invoice' => $this->has_invoice,
            'is_deductible' => $this->is_deductible,
            'iva' => $this->iva,
            'tax_amount' => $this->tax_amount,
            'taxable_base' => $this->taxable_base,
            'exempt_amount' => $this->exempt_amount,
            'total_usd' => $this->total_usd,
            'url_file' => $this->url_file,
            'file_name' => $this->file_name,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ];
            }),
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'username' => $this->user->username,
                ];
            }),
            'approved_by' => $this->whenLoaded('approvedBy', function () {
                return [
                    'id' => $this->approvedBy->id,
                    'username' => $this->approvedBy->username,
                ];
            }),
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'cancelled_by' => $this->whenLoaded('cancelledBy', function () {
                return [
                    'id' => $this->cancelledBy->id,
                    'username' => $this->cancelledBy->username,
                ];
            }),
            'cancelled_at' => $this->cancelled_at?->format('Y-m-d H:i:s'),
            'status_note' => $this->status_note,
            'audits' => $this->whenLoaded('audits', function () {
                return $this->audits->map(function ($audit) {
                    return [
                        'id' => $audit->id,
                        'action' => $audit->action,
                        'user_name' => $audit->user?->username ?? 'Sistema',
                        'old_values' => $audit->old_values,
                        'new_values' => $audit->new_values,
                        'created_at' => $audit->created_at->format('Y-m-d H:i:s'),
                    ];
                });
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
