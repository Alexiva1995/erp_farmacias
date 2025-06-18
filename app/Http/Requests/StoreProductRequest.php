<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Generalmente true si el usuario tiene los permisos adecuados,
        // puedes añadir lógica de autorización aquí si tienes gates/policies.
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
            'name' => ['required', 'string', 'max:255'],
            'active_ingredient' => ['nullable', 'string', 'max:255'],
            'laboratory_id' => ['nullable', 'exists:laboratories,id'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'origin_id' => ['nullable', 'exists:origins,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'barcode' => ['nullable', 'string', 'max:255', 'unique:products,barcode'],
            'iva' => ['boolean'],
            'psychotropic' => ['boolean'],
            'from_colombia' => ['boolean'],
            'related_product_ids' => ['nullable', 'array'],
            'related_product_ids.*' => ['exists:products,id'],
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
            'name.required' => 'El nombre del producto es obligatorio.',
            'sale_price.required' => 'El costo del producto es obligatorio.',
            'sale_price.numeric' => 'El costo debe ser un valor numérico.',
            'sale_price.min' => 'El costo no puede ser negativo.',
            'barcode.unique' => 'Ya existe un producto con este código de barra.',
            'laboratory_id.exists' => 'El laboratorio seleccionado no existe.',
            'origin_id.exists' => 'El origen seleccionado no existe.',
            'category_id.exists' => 'La categoría seleccionada no existe.',
            'related_product_ids.*.exists' => 'Uno o más productos relacionados no son válidos.',
        ];
    }
}
