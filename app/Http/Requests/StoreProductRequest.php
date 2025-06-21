<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
        $productId = $this->route('product') ? $this->route('product')->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'active_ingredient' => ['required', 'string', 'max:255'],
            'laboratory_id' => ['required', 'exists:laboratories,id'],
            'category_id' => ['required', 'exists:categories,id'],
            'origin_id' => ['required', 'exists:origins,id'],

            'cost_price' => ['required_if:id,null', 'numeric', 'gt:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],

            'barcode' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'barcode')->ignore($productId)
            ],

            'iva' => ['required', 'boolean'],
            'psychotropic' => ['required', 'boolean'],
            'from_colombia' => ['required', 'boolean'],

            'photo_url' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:2048'],

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
            'name.string' => 'El nombre del producto debe ser texto.',
            'name.max' => 'El nombre no puede exceder los 255 caracteres.',

            'active_ingredient.required' => 'El principio activo es obligatorio.',
            'active_ingredient.string' => 'El principio activo debe ser texto.',
            'active_ingredient.max' => 'El principio activo no puede exceder los 255 caracteres.',

            'laboratory_id.required' => 'Debe seleccionar un laboratorio.',
            'laboratory_id.exists' => 'El laboratorio seleccionado no es válido.',

            'category_id.required' => 'Debe seleccionar una categoría.',
            'category_id.exists' => 'La categoría seleccionada no es válida.',

            'origin_id.required' => 'Debe seleccionar un origen.',
            'origin_id.exists' => 'El origen seleccionado no es válido.',

            'cost_price.required_if' => 'El costo de compra es obligatorio para productos nuevos.',
            'cost_price.numeric' => 'El costo de compra debe ser un número.',
            'cost_price.gt' => 'El costo de compra debe ser mayor a cero.',

            'sale_price.required' => 'El costo de venta es obligatorio.',
            'sale_price.numeric' => 'El costo de venta debe ser un número.',
            'sale_price.min' => 'El costo de venta no puede ser negativo.',

            'barcode.string' => 'El código de barras debe ser texto.',
            'barcode.max' => 'El código de barras no puede exceder los 255 caracteres.',
            'barcode.unique' => 'Este código de barras ya está asignado a otro producto.',

            'iva.required' => 'Debe indicar si el producto aplica IVA.',
            'iva.boolean' => 'El valor para IVA no es válido.',
            'psychotropic.required' => 'Debe indicar si el producto es psicotrópico.',
            'psychotropic.boolean' => 'El valor para Psicotrópico no es válido.',
            'from_colombia.required' => 'Debe indicar si el producto es de Plan Colombia.',
            'from_colombia.boolean' => 'El valor para P.Colombia no es válido.',

            'photo_url.required' => 'La imagen del producto es obligatoria.',
            'photo_url.image' => 'El archivo debe ser una imagen válida.',
            'photo_url.mimes' => 'La imagen debe ser de tipo: jpeg, png, jpg, gif, svg o webp.',
            'photo_url.max' => 'La imagen no debe pesar más de 2MB.',

            'related_product_ids.array' => 'Los productos relacionados deben ser una lista.',
            'related_product_ids.*.exists' => 'Uno de los productos alternativos seleccionados no es válido.',
        ];
    }
}
