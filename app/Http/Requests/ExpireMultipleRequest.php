<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExpireMultipleRequest extends FormRequest
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
            'lot_ids' => ['required', 'array', 'min:1'],
            'lot_ids.*' => ['integer', 'exists:product_lots,id'],
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
            'lot_ids.required' => 'La lista de lotes es requerida.',
            'lot_ids.array' => 'El formato de la lista de lotes no es válido.',
            'lot_ids.min' => 'Debe seleccionar al menos un lote.',
            'lot_ids.*.integer' => 'El ID del lote debe ser un número entero.',
            'lot_ids.*.exists' => 'Uno de los lotes seleccionados no existe.',
        ];
    }
}
