<?php

declare(strict_types=1);

namespace App\Http\Resources\Wholesale;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketBenchmarkItemResource extends JsonResource
{
    /**
     * Transforma el recurso de posicionamiento de mercado a un arreglo anónimo y seguro.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'barcode' => (string) ($this->resource['barcode'] ?? ''),
            'product_id' => isset($this->resource['product_id']) ? (int) $this->resource['product_id'] : null,
            'name' => (string) ($this->resource['name'] ?? 'Sin descripción'),
            'laboratory_name' => (string) ($this->resource['laboratory_name'] ?? 'N/A'),
            'our_cost_usd' => isset($this->resource['our_cost_usd']) ? round((float) $this->resource['our_cost_usd'], 4) : null,
            'our_price_usd' => isset($this->resource['our_price_usd']) ? round((float) $this->resource['our_price_usd'], 4) : null,
            'our_stock' => isset($this->resource['our_stock']) ? (float) $this->resource['our_stock'] : 0.0,
            'our_margin_pct' => isset($this->resource['our_margin_pct']) ? round((float) $this->resource['our_margin_pct'], 2) : null,
            'best_market_price_usd' => isset($this->resource['best_market_price_usd']) ? round((float) $this->resource['best_market_price_usd'], 4) : 0.0,
            'avg_market_price_usd' => isset($this->resource['avg_market_price_usd']) ? round((float) $this->resource['avg_market_price_usd'], 4) : 0.0,
            'max_market_price_usd' => isset($this->resource['max_market_price_usd']) ? round((float) $this->resource['max_market_price_usd'], 4) : 0.0,
            'price_diff_usd' => isset($this->resource['price_diff_usd']) ? round((float) $this->resource['price_diff_usd'], 4) : 0.0,
            'price_diff_pct' => isset($this->resource['price_diff_pct']) ? round((float) $this->resource['price_diff_pct'], 2) : 0.0,
            'competitors_count' => (int) ($this->resource['competitors_count'] ?? 0),
            'market_total_stock' => (float) ($this->resource['market_total_stock'] ?? 0.0),
            'segment' => (string) ($this->resource['segment'] ?? 'uncovered'),
            'coverage_sub_type' => $this->resource['coverage_sub_type'] ?? null,
            'is_below_our_cost' => (bool) ($this->resource['is_below_our_cost'] ?? false),
        ];
    }
}
