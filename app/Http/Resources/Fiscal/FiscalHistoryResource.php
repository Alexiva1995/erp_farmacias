<?php

namespace App\Http\Resources\Fiscal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FiscalHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \App\Services\History\HistoryQueryService $historyQueryService */
        $historyQueryService = app(\App\Services\History\HistoryQueryService::class);

        return [
            'id'             => $this->id,
            'fiscal_id'      => $this->fiscal_id,
            'order_id'       => $this->order_id,
            'invoice_number' => $this->invoice_number,
            'identification' => $this->identification,
            'business_name'  => $this->business_name,
            'address'        => $this->address,
            'invoice_date'   => $this->invoice_date,
            'created_at'     => $this->created_at?->toISOString() ?? ($this->order?->created_at?->toISOString() ?? $this->invoice_date),
            'exempt_amount'        => (float) $this->exempt_amount,
            'taxable_amount'       => (float) $this->taxable_amount,
            'iva_amount'           => (float) $this->iva_amount,
            'total_amount'         => (float) $this->total_amount,
            'spe'                  => (bool) $this->spe,
            'spe_surcharge_rate'   => (float) ($this->spe_surcharge_rate ?? 0),
            'spe_surcharge_amount' => (float) ($this->spe_surcharge_amount ?? 0),
            'exchange_rate'        => (float) ($this->exchange_rate ?? 0),
            'audit_hash'           => $this->audit_hash,
            'is_audit_valid' => $historyQueryService->verifyAuditHash($this->resource),
            'user'           => $this->whenLoaded('user', function () {
                return [
                    'id'       => $this->user->id,
                    'username' => $this->user->username,
                ];
            }),
            'details'        => $this->whenLoaded('details', function () {
                return $this->details->map(function ($detail) {
                    return [
                        'id'                => $detail->id,
                        'fiscal_history_id' => $detail->fiscal_history_id,
                        'product_id'        => $detail->product_id,
                        'product_name'      => $detail->product_name,
                        'quantity'          => (float) $detail->quantity,
                        'exempt_amount'     => (float) $detail->exempt_amount,
                        'vat_status'        => (int) $detail->vat_status,
                        'total_amount'      => (float) $detail->total_amount,
                    ];
                });
            }),
        ];
    }
}
