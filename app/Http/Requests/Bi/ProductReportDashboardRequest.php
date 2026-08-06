<?php

declare(strict_types=1);

namespace App\Http\Requests\Bi;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los filtros comunes del dashboard de productos.
 */
class ProductReportDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date'     => ['nullable', 'date_format:Y-m-d'],
            'end_date'        => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'laboratory_id'  => ['nullable', 'integer', 'min:1'],
            'group_id'       => ['nullable', 'integer', 'min:1'],
        ];
    }
}
