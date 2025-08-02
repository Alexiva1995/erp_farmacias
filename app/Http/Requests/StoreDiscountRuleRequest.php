<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiscountRuleRequest extends FormRequest
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
            'scale_type' => 'required|in:units,amount',
            'rules' => 'required|array',
            'rules.*.supplier_laboratory_id' => 'required|exists:supplier_laboratories,id',
            'rules.*.min' => 'required|numeric',
            'rules.*.max' => 'required|numeric',
            'rules.*.discount_percentage' => 'required|numeric|min:0|max:100',
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
            'scale_type.required' => 'El tipo de escala es obligatorio.',
            'scale_type.in' => 'El tipo de escala debe ser "Unidad" o "Monto".',
            'rules.required' => 'Las reglas son obligatorias.',
            'rules.array' => 'Las reglas deben ser un arreglo.',
            'rules.*.supplier_laboratory_id.required' => 'El ID del laboratorio proveedor es obligatorio.',
            'rules.*.supplier_laboratory_id.exists' => 'El laboratorio proveedor seleccionado no existe.',
            'rules.*.min.required' => 'El valor mínimo es obligatorio.',
            'rules.*.min.numeric' => 'El valor mínimo debe ser un número.',
            'rules.*.max.required' => 'El valor máximo es obligatorio.',
            'rules.*.max.numeric' => 'El valor máximo debe ser un número.',
            'rules.*.discount_percentage.required' => 'El porcentaje de descuento es obligatorio.',
            'rules.*.discount_percentage.numeric' => 'El porcentaje de descuento debe ser un número.',
            'rules.*.discount_percentage.min' => 'El porcentaje de descuento no puede ser negativo.',
            'rules.*.discount_percentage.max' => 'El porcentaje de descuento no puede exceder el 100%.',
        ];
    }
}
