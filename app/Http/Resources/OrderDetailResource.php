<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailResource extends JsonResource
{
    /**
     * Transforma una orden con su detalle completo para vista de modal e impresión.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'seller_id' => $this->seller_id,
            'cash_closing_id' => $this->cash_closing_id,
            'total_amount' => (float) $this->total_amount,
            'total_amount_usd' => $this->total_amount_usd !== null ? (float) $this->total_amount_usd : null,
            'money_returns' => (float) ($this->money_returns ?? 0),
            'currency' => $this->currency,
            'taxable_base' => $this->taxable_base !== null ? (float) $this->taxable_base : 0.0,
            'spe_surcharge_rate' => $this->spe_surcharge_rate !== null ? (float) $this->spe_surcharge_rate : 0.0,
            'spe_surcharge_amount' => $this->spe_surcharge_amount !== null ? (float) $this->spe_surcharge_amount : 0.0,
            'order_date' => $this->order_date ? (is_string($this->order_date) ? $this->order_date : $this->order_date->format('Y-m-d H:i:s')) : null,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'status' => $this->status,
            'has_multiple_currencies' => (bool) $this->has_multiple_currencies,
            'payment_methods' => $this->payment_methods,
            'dining_table_id' => $this->dining_table_id,
            'parent_order_id' => $this->parent_order_id,
            'guests_count' => $this->guests_count !== null ? (int) $this->guests_count : null,
            'service_charge_percentage' => $this->service_charge_percentage !== null ? (float) $this->service_charge_percentage : 0.0,
            'service_charge_amount' => $this->service_charge_amount !== null ? (float) $this->service_charge_amount : 0.0,
            'service_charge_amount_usd' => $this->service_charge_amount_usd !== null ? (float) $this->service_charge_amount_usd : 0.0,
            'pre_bill_printed_at' => $this->pre_bill_printed_at ? (is_string($this->pre_bill_printed_at) ? $this->pre_bill_printed_at : $this->pre_bill_printed_at->format('Y-m-d H:i:s')) : null,
            'client' => $this->whenLoaded('client', function () {
                if (!$this->client) {
                    return null;
                }

                return [
                    'id' => $this->client->id,
                    'identification_type' => $this->client->identification_type,
                    'identification' => $this->client->identification,
                    'name' => $this->client->name,
                    'last_name' => $this->client->last_name,
                    'phone' => $this->client->phone ?? null,
                    'address' => $this->client->address ?? null,
                ];
            }),
            'seller' => $this->whenLoaded('seller', function () {
                if (!$this->seller) {
                    return null;
                }

                return [
                    'id' => $this->seller->id,
                    'username' => $this->seller->username,
                    'name' => $this->seller->name ?? null,
                ];
            }),
            'dining_table' => $this->whenLoaded('diningTable', function () {
                if (!$this->diningTable) {
                    return null;
                }

                return [
                    'id' => $this->diningTable->id,
                    'name' => $this->diningTable->name,
                    'capacity' => $this->diningTable->capacity,
                    'zone' => $this->diningTable->relationLoaded('zone') && $this->diningTable->zone
                        ? ['id' => $this->diningTable->zone->id, 'name' => $this->diningTable->zone->name]
                        : null,
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
            'returns_count' => $this->whenLoaded('returns', fn () => $this->returns->count(), (int) ($this->returns_count ?? 0)),
            'psychotropic_controls_count' => $this->whenLoaded('psychotropicControls', fn () => $this->psychotropicControls->count(), (int) ($this->psychotropic_controls_count ?? 0)),
            'child_orders' => $this->whenLoaded('childOrders', function () {
                return $this->childOrders->map(fn ($child) => [
                    'id' => $child->id,
                    'status' => $child->status,
                    'total_amount' => (float) $child->total_amount,
                    'currency' => $child->currency,
                ])->values();
            }),
            'details' => $this->whenLoaded('details', function () {
                return $this->details->map(function ($detail) {
                    $kitchenStatus = $detail->kitchen_status;
                    $kitchenStatusValue = $kitchenStatus instanceof \BackedEnum ? $kitchenStatus->value : $kitchenStatus;
                    $kitchenStatusLabel = ($kitchenStatus instanceof \App\Enums\KitchenStatusEnum)
                        ? $kitchenStatus->label()
                        : $kitchenStatusValue;

                    return [
                        'id' => $detail->id,
                        'order_id' => $detail->order_id,
                        'product_id' => $detail->product_id,
                        'dish_id' => $detail->dish_id,
                        'court_id' => $detail->court_id,
                        'pack_id' => $detail->pack_id,
                        'product_type' => $detail->product_type,
                        'product_lot_id' => $detail->product_lot_id,
                        'quantity' => (float) $detail->quantity,
                        'price' => (float) $detail->price,
                        'price_bs' => $detail->price_bs !== null ? (float) $detail->price_bs : null,
                        'unit_price_usd' => $detail->unit_price_usd !== null ? (float) $detail->unit_price_usd : null,
                        'price_before_discount' => $detail->price_before_discount !== null ? (float) $detail->price_before_discount : null,
                        'discount_percentage' => $detail->discount_percentage !== null ? (float) $detail->discount_percentage : 0.0,
                        'discount_type' => $detail->discount_type,
                        'notes' => $detail->notes,
                        'seat_number' => $detail->seat_number,
                        'course_number' => $detail->course_number,
                        'kitchen_status' => $kitchenStatusValue,
                        'kitchen_status_label' => $kitchenStatusLabel,
                        'commanded_at' => $detail->commanded_at ? $detail->commanded_at->format('Y-m-d H:i:s') : null,
                        'prepared_at' => $detail->prepared_at ? $detail->prepared_at->format('Y-m-d H:i:s') : null,
                        'product' => $detail->relationLoaded('product') && $detail->product ? [
                            'id' => $detail->product->id,
                            'name' => $detail->product->name,
                            'active_ingredient' => $detail->product->active_ingredient ?? null,
                            'iva' => (int) ($detail->product->iva ?? 0),
                            'psychotropic' => (bool) ($detail->product->psychotropic ?? false),
                            'laboratory' => $detail->product->relationLoaded('laboratory') && $detail->product->laboratory ? [
                                'id' => $detail->product->laboratory->id,
                                'name' => $detail->product->laboratory->name,
                            ] : null,
                        ] : null,
                        'dish' => $detail->relationLoaded('dish') && $detail->dish ? [
                            'id' => $detail->dish->id,
                            'name' => $detail->dish->name,
                            'iva' => (bool) ($detail->dish->iva ?? false),
                        ] : null,
                        'court' => $detail->relationLoaded('court') && $detail->court ? [
                            'id' => $detail->court->id,
                            'name' => $detail->court->name,
                        ] : null,
                        'lot' => $detail->relationLoaded('lot') && $detail->lot ? [
                            'id' => $detail->lot->id,
                            'lot_number' => $detail->lot->lot_number,
                            'expiration_date' => $detail->lot->expiration_date
                                ? (is_string($detail->lot->expiration_date) ? $detail->lot->expiration_date : $detail->lot->expiration_date->format('Y-m-d'))
                                : null,
                            'location' => $detail->lot->location ?? null,
                        ] : null,
                        'modifiers' => $detail->relationLoaded('modifiers')
                            ? $detail->modifiers->map(fn ($mod) => [
                                'id' => $mod->id,
                                'name' => $mod->name,
                                'modifier_type' => $mod->modifier_type,
                                'extra_price_usd' => (float) ($mod->extra_price_usd ?? 0),
                            ])->values()
                            : [],
                    ];
                })->values();
            }),
        ];
    }
}
