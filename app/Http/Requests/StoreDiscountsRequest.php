<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiscountsRequest extends FormRequest
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
            'discounts' => 'required|array',
            'discounts.*.name' => 'required',
            'discounts.*.discount_percentage' => 'required|numeric|min:0|max:100'
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
            'discounts.required' => 'Los descuentos son obligatorios.',
            'discounts.array' => 'Los descuentos deben ser un arreglo.',

            'discounts.*.name.required' => 'Los nombres son obligatorios.',

            'discounts.*.discount_percentage.required' => 'El porcentaje de descuento es obligatorio.',
            'discounts.*.discount_percentage.numeric' => 'El porcentaje de descuento debe ser un número.',
            'discounts.*.discount_percentage.min' => 'El porcentaje de descuento no puede ser negativo.',
            'discounts.*.discount_percentage.max' => 'El porcentaje de descuento no puede exceder el 100%.',
        ];
    }
}
