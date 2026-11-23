<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderListItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hasFiscalInvoice = $this->relationLoaded('fiscalHistory')
            && !empty($this->fiscalHistory?->invoice_number);

        $isCashOpen = $this->relationLoaded('cashClosing')
            && $this->cashClosing !== null
            && $this->cashClosing->status === \App\Models\CashClosing::OPEN
            && empty($this->cashClosing->daily_closure_id);

        $isOpenOrder = in_array($this->status, [\App\Models\Order::PENDING, \App\Models\Order::RESERVED], true);
        $isCompleted = $this->status === \App\Models\Order::COMPLETED;

        // Solo se permite cancelar órdenes abiertas o completadas de caja abierta sin factura fiscal
        $canCancel = $isOpenOrder || ($isCompleted && !$hasFiscalInvoice && $isCashOpen);
        $canReturn = $isCompleted && !$canCancel;

        return [
            'id' => $this->id,
            'order_date' => $this->order_date ? (is_string($this->order_date) ? $this->order_date : $this->order_date->format('Y-m-d H:i:s')) : null,
            'total_amount' => (float) $this->total_amount,
            'total_amount_usd' => $this->total_amount_usd !== null ? (float) $this->total_amount_usd : null,
            'currency' => $this->currency,
            'status' => $this->status,
            'has_fiscal_invoice' => $hasFiscalInvoice,
            'can_cancel' => $canCancel,
            'can_return' => $canReturn,
            'has_multiple_currencies' => (bool) $this->has_multiple_currencies,
            'spe_surcharge_rate' => $this->spe_surcharge_rate !== null ? (float) $this->spe_surcharge_rate : 0.0,
            'spe_surcharge_amount' => $this->spe_surcharge_amount !== null ? (float) $this->spe_surcharge_amount : 0.0,
            'dining_table_id' => $this->dining_table_id,
            'parent_order_id' => $this->parent_order_id,
            'guests_count' => $this->guests_count !== null ? (int) $this->guests_count : null,
            'service_charge_percentage' => $this->service_charge_percentage !== null ? (float) $this->service_charge_percentage : 0.0,
            'service_charge_amount' => $this->service_charge_amount !== null ? (float) $this->service_charge_amount : 0.0,
            'pre_bill_printed_at' => $this->pre_bill_printed_at ? (is_string($this->pre_bill_printed_at) ? $this->pre_bill_printed_at : $this->pre_bill_printed_at->format('Y-m-d H:i:s')) : null,
            'returns_count' => (int) ($this->returns_count ?? 0),
            'psychotropic_controls_count' => (int) ($this->psychotropic_controls_count ?? 0),
            'payment_methods' => $this->payment_methods,
            'client' => $this->whenLoaded('client', function () {
                return [
                    'id' => $this->client->id,
                    'identification_type' => $this->client->identification_type,
                    'identification' => $this->client->identification,
                    'name' => $this->client->name,
                    'last_name' => $this->client->last_name,
                ];
            }),
            'seller' => $this->whenLoaded('seller', function () {
                return [
                    'id' => $this->seller->id,
                    'username' => $this->seller->username,
                ];
            }),
            'dining_table' => $this->whenLoaded('diningTable', function () {
                if (!$this->diningTable) {
                    return null;
                }

                return [
                    'id' => $this->diningTable->id,
                    'name' => $this->diningTable->name,
                ];
            }),
            'fiscal_history' => $this->whenLoaded('fiscalHistory', function () {
                if (!$this->fiscalHistory) {
                    return null;
                }

                return [
                    'id' => $this->fiscalHistory->id,
                    'invoice_number' => $this->fiscalHistory->invoice_number,
                    'exempt_amount' => (float) $this->fiscalHistory->exempt_amount,
                    'taxable_amount' => (float) $this->fiscalHistory->taxable_amount,
                    'iva_amount' => (float) $this->fiscalHistory->iva_amount,
                    'total_amount' => (float) $this->fiscalHistory->total_amount,
                    'spe_surcharge_rate' => (float) $this->fiscalHistory->spe_surcharge_rate,
                    'spe_surcharge_amount' => (float) $this->fiscalHistory->spe_surcharge_amount,
                    'exchange_rate' => (float) $this->fiscalHistory->exchange_rate,
                    'is_queued' => (bool) $this->fiscalHistory->is_queued,
                    'spe' => (bool) $this->fiscalHistory->spe,
                ];
            }),
        ];
    }
}
