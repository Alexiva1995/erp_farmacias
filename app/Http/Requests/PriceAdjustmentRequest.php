<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PriceAdjustmentRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'month' => ['required', 'string', 'date_format:Y-m'],
            'excludedProductIds' => ['sometimes', 'array'],
            'excludedProductIds.*' => ['integer', 'exists:products,id'],
        ];
    }

    /**
     * Obtiene los mensajes de error para las reglas de validación definidas.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'month.required' => 'El mes es requerido.',
            'month.date_format' => 'El formato del mes debe ser YYYY-MM.',
            'excludedProductIds.array' => 'El formato de productos excluidos no es válido.',
            'excludedProductIds.*.integer' => 'El ID del producto excluido debe ser un número entero.',
            'excludedProductIds.*.exists' => 'Uno de los productos excluidos no existe.',
        ];
    }
}
