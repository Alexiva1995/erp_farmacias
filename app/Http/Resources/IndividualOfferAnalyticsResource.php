<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IndividualOfferAnalyticsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $offer = $this->resource['offer'] ?? null;
        $kpis = $this->resource['kpis'] ?? [];
        $salePrice = (float) ($offer?->product?->sale_price ?? 0);
        $discountPercent = (float) ($offer?->discount_percent ?? 0);
        $offerPrice = round($salePrice * (1 - ($discountPercent / 100)), 2);

        return [
            'offer' => [
                'id' => $offer?->id,
                'product_id' => $offer?->product_id,
                'product_name' => $offer?->product?->name ?? 'N/A',
                'active_ingredient' => $offer?->product?->active_ingredient ?? 'N/A',
                'laboratory_name' => $offer?->product?->laboratory?->name ?? 'S/L',
                'discount_percent' => $discountPercent,
                'start_date' => $offer?->start_date,
                'end_date' => $offer?->end_date,
                'normal_price' => $salePrice,
                'offer_price' => $offerPrice,
            ],
            'kpis' => [
                'total_units_sold' => (float) ($kpis['total_units_sold'] ?? 0),
                'unique_clients_count' => (int) ($kpis['unique_clients_count'] ?? 0),
                'total_orders_count' => (int) ($kpis['total_orders_count'] ?? 0),
                'cross_sell_orders_count' => (int) ($kpis['cross_sell_orders_count'] ?? 0),
                'single_item_orders_count' => (int) ($kpis['single_item_orders_count'] ?? 0),
                'cross_sell_percentage' => (float) ($kpis['cross_sell_percentage'] ?? 0),
                'average_ticket_usd' => (float) ($kpis['average_ticket_usd'] ?? 0),
                'total_orders_amount_usd' => (float) ($kpis['total_orders_amount_usd'] ?? 0),
                'offer_revenue_usd' => (float) ($kpis['offer_revenue_usd'] ?? 0),
                'offer_cost_usd' => (float) ($kpis['offer_cost_usd'] ?? 0),
                'offer_profit_usd' => (float) ($kpis['offer_profit_usd'] ?? 0),
                'offer_profit_margin' => (float) ($kpis['offer_profit_margin'] ?? 0),
                'discount_savings_usd' => (float) ($kpis['discount_savings_usd'] ?? 0),
            ],
            'cross_selling_products' => $this->resource['cross_selling_products'] ?? [],
            'sellers_breakdown' => $this->resource['sellers_breakdown'] ?? [],
            'available_sellers' => $this->resource['available_sellers'] ?? [],
        ];
    }
}
