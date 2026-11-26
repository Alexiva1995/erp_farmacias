<?php

namespace App\Http\Resources\Finances;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryProfitabilityResource extends JsonResource
{
    /**
     * Transforma la categoría y su configuración de rentabilidad en un array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $prof = $this->relationLoaded('profitability') ? $this->profitability : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'has_rule' => $prof !== null,
            'profitability' => $prof ? [
                'id' => $prof->id,
                'category_id' => (int) $prof->category_id,
                'profitability_percentage' => $prof->profitability_percentage !== null ? (float) $prof->profitability_percentage : null,
                'shipping_cost' => $prof->shipping_cost !== null ? (float) $prof->shipping_cost : null,
                'packaging_cost' => $prof->packaging_cost !== null ? (float) $prof->packaging_cost : null,
                'expense_margin' => $prof->expense_margin !== null ? (float) $prof->expense_margin : null,
                'profit_margin' => $prof->profit_margin !== null ? (float) $prof->profit_margin : null,
                'tax_usa' => $prof->tax_usa !== null ? (float) $prof->tax_usa : null,
                'status' => $prof->status,
            ] : null,
        ];
    }
}
