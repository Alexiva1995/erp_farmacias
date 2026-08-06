<?php

declare(strict_types=1);

namespace App\Http\Requests\Bi;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los filtros del ranking paginado de productos.
 */
class ProductReportRankingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date'    => ['nullable', 'date_format:Y-m-d'],
            'end_date'       => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'laboratory_id' => ['nullable', 'integer', 'min:1'],
            'group_id'      => ['nullable', 'integer', 'min:1'],
            'sort_by'       => ['nullable', 'string', 'in:total_sold,total_revenue,total_margin'],
            'page'          => ['nullable', 'integer', 'min:1'],
            'search'        => ['nullable', 'string', 'max:100'],
        ];
    }
}
