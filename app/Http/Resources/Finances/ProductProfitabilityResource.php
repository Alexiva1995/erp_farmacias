<?php

namespace App\Http\Resources\Finances;

use App\Models\ProfitabilitySettings;
use App\Services\ProfitabilityServices;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class ProductProfitabilityResource extends JsonResource
{
    /**
     * Caché por conexión para evitar consultas repetidas de configuración global al serializar colecciones.
     *
     * @var array<string, ProfitabilitySettings|null>
     */
    protected static array $globalSettingsCache = [];

    /**
     * Transforma el recurso en un array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $connKey = DB::getDefaultConnection() . ':' . (function_exists('tenancy') && tenancy()->initialized ? (string) tenancy()->tenant?->id : 'central');
        if (!array_key_exists($connKey, static::$globalSettingsCache)) {
            static::$globalSettingsCache[$connKey] = ProfitabilitySettings::orderBy('created_at', 'desc')->first();
        }

        $effective = app(ProfitabilityServices::class)->resolveEffectiveParameters(
            $this->resource,
            static::$globalSettingsCache[$connKey]
        );

        return [
            'id' => $this->id,
            'name' => $this->name,
            'unit_cost' => (float) $this->unit_cost,
            'sale_price' => (float) $this->sale_price,
            'active_ingredient' => $this->active_ingredient,
            'psychotropic' => (bool) $this->psychotropic,
            'is_colombian_origin' => (bool) $this->is_colombian_origin,
            'iva' => $this->iva,
            'category_id' => $this->category_id,
            'category' => $this->relationLoaded('category') && $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null,
            'laboratory' => $this->relationLoaded('laboratory') && $this->laboratory ? [
                'id' => $this->laboratory->id,
                'name' => $this->laboratory->name,
            ] : null,
            'profitability' => $this->relationLoaded('profitability') && $this->profitability ? [
                'id' => $this->profitability->id,
                'profitability_percentage' => (float) $this->profitability->profitability_percentage,
                'is_locked' => (int) $this->profitability->is_locked,
                'shipping_cost' => $this->profitability->shipping_cost !== null ? (float) $this->profitability->shipping_cost : null,
                'packaging_cost' => $this->profitability->packaging_cost !== null ? (float) $this->profitability->packaging_cost : null,
                'expense_margin' => $this->profitability->expense_margin !== null ? (float) $this->profitability->expense_margin : null,
                'profit_margin' => $this->profitability->profit_margin !== null ? (float) $this->profitability->profit_margin : null,
                'tax_usa' => $this->profitability->tax_usa !== null ? (float) $this->profitability->tax_usa : null,
            ] : null,
            'effective_profitability' => $effective,
        ];
    }
}
