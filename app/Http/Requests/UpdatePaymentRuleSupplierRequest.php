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
            'days' => 'required|integer|min:1',
            'discount_percentage' => 'required|numeric|min:0|max:100'
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
            'days.required' => 'Los días son obligatorios.',
            'days.integer' => 'Los días deben ser un número entero.',
            'days.min' => 'Los días deben ser al menos 1.',

            'discount_percentage.required' => 'El porcentaje de descuento es obligatorio.',
            'discount_percentage.numeric' => 'El porcentaje de descuento debe ser un número.',
            'discount_percentage.min' => 'El porcentaje de descuento no puede ser negativo.',
            'discount_percentage.max' => 'El porcentaje de descuento no puede exceder el 100%.',
        ];
    }
}
