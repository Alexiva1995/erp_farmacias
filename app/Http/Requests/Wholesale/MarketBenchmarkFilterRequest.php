<?php

declare(strict_types=1);

namespace App\Http\Requests\Wholesale;

use Illuminate\Foundation\Http\FormRequest;

class MarketBenchmarkFilterRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para los filtros del dashboard de inteligencia de mercado.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'segment' => ['nullable', 'string', 'in:cheaper,more_expensive,uncovered'],
            'search' => ['nullable', 'string', 'max:150'],
            'laboratory_id' => ['nullable', 'integer'],
            'coverage_type' => ['nullable', 'string', 'in:all,not_created,zero_stock'],
            'price_diff_min' => ['nullable', 'numeric'],
            'price_diff_max' => ['nullable', 'numeric'],
            'min_competitors' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
