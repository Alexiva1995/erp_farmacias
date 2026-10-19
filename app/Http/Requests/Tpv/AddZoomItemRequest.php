<?php

namespace App\Http\Requests\Tpv;

use Illuminate\Foundation\Http\FormRequest;

class AddZoomItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'amount_net' => ['required', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'string', 'in:USD,BS,COP'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ];
    }

    /**
     * Custom message for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'description.required' => 'La descripción del envío o servicio Zoom es obligatoria.',
            'description.string' => 'La descripción debe ser una cadena de texto válida.',
            'description.max' => 'La descripción no debe exceder los 255 caracteres.',
            'amount_net.required' => 'El monto sin IVA es obligatorio.',
            'amount_net.numeric' => 'El monto sin IVA debe ser un valor numérico.',
            'amount_net.gt' => 'El monto sin IVA debe ser mayor a 0.',
            'currency.in' => 'La moneda seleccionada debe ser USD, BS o COP.',
            'client_id.exists' => 'El cliente especificado no existe.',
        ];
    }
}
