<?php

namespace App\Http\Requests\Bi;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Clase AbcReportRequest
 * 
 * Se encarga de validar los filtros del reporte ABC Multicriterio.
 */
class AbcReportRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtener las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date', 'before_or_equal:end_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'laboratory_id' => ['nullable'],
            'final_classification' => ['nullable', 'string', 'size:3', 'regex:/^[ABC][ABC][XYZ]$/i'],
            'page' => ['nullable', 'integer', 'min:1'],
            'itemsPerPage' => ['nullable', 'integer', 'min:-1'],
            'sortBy' => ['nullable', 'string'],
            'orderBy' => ['nullable', 'string', 'in:asc,desc'],
            'analysis_type' => ['nullable', 'string', 'in:all,dead_stock,star_products'],
            'min_gmroi' => ['nullable', 'numeric'],
            'stock_filter' => ['nullable', 'string', 'in:all,with_stock,out_of_stock'],
            'search' => ['nullable', 'string'],
        ];
    }
}
