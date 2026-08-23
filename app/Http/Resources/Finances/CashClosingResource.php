<?php

namespace App\Http\Resources\Finances;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashClosingResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'seller_id' => $this->seller_id,
            'closing_date' => $this->closing_date,
            'status' => $this->status,
            'total_sales' => (float)$this->total_sales,
            'total_usd' => (float)$this->total_usd,
            'total_cop' => (float)$this->total_cop,
            'total_bs' => (float)$this->total_bs,
            'bs_card_debito' => (float)$this->bs_card_debito,
            'bs_card_credit' => (float)$this->bs_card_credit,
            'bs_cash' => (float)$this->bs_cash,
            'bs_transfer' => (float)$this->bs_transfer,
            'bs_mobile' => (float)$this->bs_mobile,
            'cop_cash' => (float)$this->cop_cash,
            'cop_transfer' => (float)$this->cop_transfer,
            'cop_conversion' => (float)$this->cop_conversion,
            'cop_spare' => (float)$this->cop_spare,
            'usd_transfer' => (float)$this->usd_transfer,
            'usd_cash' => (float)$this->usd_cash,
            'usd_paypal' => (float)$this->usd_paypal,
            'usd_binance' => (float)$this->usd_binance,
            'usd_conversion' => (float)$this->usd_conversion,
            'usd_credit' => (float)$this->usd_credit,
            'usd_balance' => (float)$this->usd_balance,
            'usd_delivered' => (float)$this->usd_delivered,
            'cop_delivered' => (float)$this->cop_delivered,
            'bs_delivered' => (float)$this->bs_delivered,
            'declared_cop' => (float)($this->declared_cop ?? 0),
            'declared_cop_transfer' => (float)($this->declared_cop_transfer ?? 0),
            'declared_usd' => (float)($this->declared_usd ?? 0),
            'declared_credit' => (float)($this->declared_credit ?? 0),
            'declared_bs_mobile' => (float)($this->declared_bs_mobile ?? 0),
            'declared_bs_card' => (float)($this->declared_bs_card ?? 0),
            'diff_cop' => (float)($this->diff_cop ?? 0.00),
            'diff_cop_transfer' => (float)($this->diff_cop_transfer ?? 0.00),
            'diff_usd' => (float)($this->diff_usd ?? 0.00),
            'diff_credit' => (float)($this->diff_credit ?? 0.00),
            'diff_bs_mobile' => (float)($this->diff_bs_mobile ?? 0.00),
            'diff_bs_card' => (float)($this->diff_bs_card ?? 0.00),
            'blind_mismatches' => is_string($this->blind_mismatches) 
                ? json_decode($this->blind_mismatches, true) 
                : ($this->blind_mismatches ?? []),
            'blind_note' => $this->blind_note,
            'exchange_rate' => (float)$this->exchange_rate,
            'cop_exchange_rate' => (float)$this->cop_exchange_rate,
            'seller' => $this->relationLoaded('seller') ? [
                'id' => $this->seller->id,
                'username' => $this->seller->username,
            ] : null,
            'orders' => $this->relationLoaded('orders') ? $this->orders->map(function($order) {
                return [
                    'id' => $order->id,
                    'total_amount' => (float)$order->total_amount,
                    'currency' => $order->currency,
                    'order_date' => $order->order_date,
                    'payment_methods' => is_string($order->payment_methods) 
                        ? json_decode($order->payment_methods, true) 
                        : ($order->payment_methods ?? []),
                    'details' => $order->relationLoaded('details') ? $order->details->map(function($detail) {
                        return [
                            'id' => $detail->id,
                            'product_id' => $detail->product_id,
                            'quantity' => $detail->quantity,
                            'price' => (float)$detail->price,
                            'product' => $detail->relationLoaded('product') && $detail->product ? [
                                'id' => $detail->product->id,
                                'name' => $detail->product->name,
                            ] : null,
                        ];
                    }) : [],
                ];
            }) : [],
            'credit_payments' => $this->relationLoaded('creditPayments') ? $this->creditPayments->map(function($payment) {
                return [
                    'id' => $payment->id,
                    'client_id' => $payment->client_id,
                    'money_returns' => (float)$payment->money_returns,
                    'payment_date' => $payment->payment_date,
                    'method_Payment' => is_string($payment->method_Payment) 
                        ? json_decode($payment->method_Payment, true) 
                        : ($payment->method_Payment ?? []),
                    'client' => $payment->relationLoaded('client') && $payment->client ? [
                        'id' => $payment->client->id,
                        'name' => $payment->client->name,
                        'identification' => $payment->client->identification,
                    ] : null,
                ];
            }) : [],
        ];
    }
}
