<?php

namespace App\Http\Resources\Finances;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductProfitabilityResource extends JsonResource
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
            'name' => $this->name,
            'unit_cost' => (float)$this->unit_cost,
            'active_ingredient' => $this->active_ingredient,
            'psychotropic' => (bool)$this->psychotropic,
            'is_colombian_origin' => (bool)$this->is_colombian_origin,
            'iva' => $this->iva,
            'stock_calculado' => (float)$this->stock_calculado,
            'laboratory' => $this->relationLoaded('laboratory') && $this->laboratory ? [
                'id' => $this->laboratory->id,
                'name' => $this->laboratory->name,
            ] : null,
            'profitability' => $this->relationLoaded('profitability') && $this->profitability ? [
                'id' => $this->profitability->id,
                'profitability_percentage' => (float)$this->profitability->profitability_percentage,
                'is_locked' => (int)$this->profitability->is_locked,
                'shipping_cost' => $this->profitability->shipping_cost !== null ? (float)$this->profitability->shipping_cost : null,
                'packaging_cost' => $this->profitability->packaging_cost !== null ? (float)$this->profitability->packaging_cost : null,
                'expense_margin' => $this->profitability->expense_margin !== null ? (float)$this->profitability->expense_margin : null,
                'profit_margin' => $this->profitability->profit_margin !== null ? (float)$this->profitability->profit_margin : null,
                'tax_usa' => $this->profitability->tax_usa !== null ? (float)$this->profitability->tax_usa : null,
            ] : null,
        ];
    }
}
