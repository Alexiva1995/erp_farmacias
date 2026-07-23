<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFurnitureRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'cost' => ['required', 'numeric', 'min:0.01'],
            'acquisition_year' => ['required', 'integer', 'min:2000', 'max:' . date('Y')],
            'annual_depreciation_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
