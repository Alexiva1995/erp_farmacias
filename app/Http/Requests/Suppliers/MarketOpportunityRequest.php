<?php

namespace App\Http\Requests\Suppliers;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Clase MarketOpportunityRequest
 * 
 * Se encarga de validar los parámetros de entrada del endpoint de oportunidades.
 */
class MarketOpportunityRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // Se asume autenticación global por middleware
    }

    /**
     * Obtener las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'itemsPerPage' => ['nullable', 'integer', 'min:1'],
            'sortBy' => ['nullable', 'string'],
            'orderBy' => ['nullable', 'string', 'in:asc,desc'],
            'q' => ['nullable', 'string'],
            'laboratoryId' => ['nullable'],
            'productId' => ['nullable'],
        ];
    }
}
