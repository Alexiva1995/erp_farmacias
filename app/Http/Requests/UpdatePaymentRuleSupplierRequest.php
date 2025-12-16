<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRuleSupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'rules' => 'required|array',
            'rules.*.days' => 'required|integer|min:0',
            'rules.*.id' => 'nullable|integer',
            'rules.*.discount_percentage' => 'required|numeric|min:0|max:100'
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'rules.required' => 'Las reglas son obligatorias.',
            'rules.array' => 'Las reglas deben ser un arreglo.',

            'rules.*.days.required' => 'Los días son obligatorios.',
            'rules.*.days.integer' => 'Los días deben ser un número entero.',
            'rules.*.days.min' => 'Los días no pueden ser negativos.',

            'rules.*.discount_percentage.required' => 'El porcentaje de descuento es obligatorio.',
            'rules.*.discount_percentage.numeric' => 'El porcentaje de descuento debe ser un número.',
            'rules.*.discount_percentage.min' => 'El porcentaje de descuento no puede ser negativo.',
            'rules.*.discount_percentage.max' => 'El porcentaje de descuento no puede exceder el 100%.',
        ];
    }
}
