<?php

namespace App\Http\Resources\Finances;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyCashClosureResource extends JsonResource
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
            'id'                   => $this->id,
            'total_sales'          => (float)$this->total_sales,
            'total_usd'            => (float)$this->total_usd,
            'total_cop'            => (float)$this->total_cop,
            'total_bs'             => (float)$this->total_bs,
            // USD por método
            'usd_cash'             => (float)$this->usd_cash,
            'usd_transfer'         => (float)$this->usd_transfer,
            'usd_paypal'           => (float)$this->usd_paypal,
            'usd_binance'          => (float)$this->usd_binance,
            'usd_delivered'        => (float)$this->usd_delivered,
            'total_credits'        => (float)$this->total_credits,
            // COP por método
            'cop_cash'             => (float)$this->cop_cash,
            'cop_transfer'         => (float)$this->cop_transfer,
            'cop_delivered'        => (float)$this->cop_delivered,
            // Bs por método
            'bs_cash'              => (float)$this->bs_cash,
            'bs_card_debito'       => (float)$this->bs_card_debito,
            'bs_card_credit'       => (float)$this->bs_card_credit,
            'bs_transfer'          => (float)$this->bs_transfer,
            'bs_mobile'            => (float)$this->bs_mobile,
            'bs_card'              => (float)$this->bs_card,
            'bs_delivered'         => (float)$this->bs_delivered,
            // Totales
            'total_payment_credit' => (float)$this->total_payment_credit,
            'total_delivery'       => (float)$this->total_delivery,
            'created_at'           => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'updated_at'           => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
            'exchange_rate'        => (float)$this->exchange_rate,
            'cop_exchange_rate'    => (float)$this->cop_exchange_rate,
            'cash_closings'        => CashClosingResource::collection($this->whenLoaded('cashClosings')),
        ];
    }
}
