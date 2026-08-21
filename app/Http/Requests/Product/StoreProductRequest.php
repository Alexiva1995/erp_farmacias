<?php

namespace App\Http\Requests\Product;

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
        $productId    = $this->route('product') ? $this->route('product')->id : null;
        $setting      = \App\Models\GeneralSetting::first();
        $isRestaurant = $setting?->business_type === 'restaurant';

        // Campos habilitados según configuración; si no hay configuración, habilitamos todos
        $enabledFields = $setting?->product_form_fields ?? null;
        $fieldEnabled  = fn(string $key): bool => $enabledFields === null || in_array($key, $enabledFields, true);

        return [
            // Nombre: siempre requerido si está habilitado
            'name' => $fieldEnabled('name') ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],

            'description' => ['nullable', 'string'],

            // Principio Activo: solo required cuando no es restaurante Y está habilitado en config
            'active_ingredient' => ($isRestaurant || !$fieldEnabled('active_ingredient'))
                ? ['nullable', 'string', 'max:255']
                : ['required', 'string', 'max:255'],

            // Laboratorio: required si está habilitado
            'laboratory_id' => $fieldEnabled('laboratory_id')
                ? ['required', 'exists:laboratories,id']
                : ['nullable', 'exists:laboratories,id'],

            // Categoría: required si está habilitado
            'category_id' => $fieldEnabled('category_id')
                ? ['required', 'exists:categories,id']
                : ['nullable', 'exists:categories,id'],

            // Origen: required solo en farmacias y si está habilitado
            'origin_id' => ($isRestaurant || !$fieldEnabled('origin_id'))
                ? ['nullable', 'exists:origins,id']
                : ['required', 'exists:origins,id'],

            'unit_cost'     => ['nullable', 'numeric', 'min:0'],
            'sale_price'    => ['nullable', 'numeric', 'min:0'],
            'initial_stock' => ['nullable', 'numeric', 'min:0'],

            'barcode' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'barcode')->ignore($productId)
            ],

            // Booleanos: required solo si el campo está habilitado en config
            'iva'              => $fieldEnabled('iva')              ? ['required', 'boolean'] : ['sometimes', 'boolean'],
            'psychotropic'     => $fieldEnabled('psychotropic')     ? ['required', 'boolean'] : ['sometimes', 'boolean'],
            'is_colombian_origin' => $fieldEnabled('is_colombian_origin') ? ['required', 'boolean'] : ['sometimes', 'boolean'],
            'is_novaventa'     => ['sometimes', 'boolean'],
            'no_pvp'           => ['sometimes', 'boolean'],

            'photo_url'        => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:2048'],
            'group_id'         => 'nullable|integer|exists:groups_products,id',
            'is_scarce'        => ['sometimes', 'boolean'],
            'is_unified_group' => ['sometimes', 'boolean'],
            'presentation'     => ['nullable', 'numeric', 'min:0'],
            'unit_of_measure'  => ['nullable', 'string', 'in:g,ml,und'],
            'supplier_id'      => ['nullable', 'integer', 'exists:suppliers,id'],
            'supplier_ids'     => ['sometimes', 'array'],
            'supplier_ids.*'   => ['integer', 'exists:suppliers,id'],
            'master_id'        => ['nullable', 'integer'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_colombian_origin' => filter_var($this->input('is_colombian_origin'), FILTER_VALIDATE_BOOLEAN),
            'is_novaventa' => filter_var($this->input('is_novaventa'), FILTER_VALIDATE_BOOLEAN),
            'psychotropic' => filter_var($this->input('psychotropic'), FILTER_VALIDATE_BOOLEAN),
            'iva' => filter_var($this->input('iva'), FILTER_VALIDATE_BOOLEAN),
            'is_scarce' => filter_var($this->input('is_scarce'), FILTER_VALIDATE_BOOLEAN),
            'is_unified_group' => filter_var($this->input('is_unified_group'), FILTER_VALIDATE_BOOLEAN),
            'no_pvp' => filter_var($this->input('no_pvp'), FILTER_VALIDATE_BOOLEAN),
        ]);
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

            'unit_cost.numeric' => 'El costo de compra debe ser un número.',
            'unit_cost.min' => 'El costo de compra no puede ser negativo.',

            'sale_price.numeric' => 'El precio de venta debe ser un número.',
            'sale_price.min' => 'El precio de venta no puede ser negativo.',

            'barcode.string' => 'El código de barras debe ser texto.',
            'barcode.max' => 'El código de barras no puede exceder los 255 caracteres.',
            'barcode.unique' => 'Este código de barras ya está asignado a otro producto.',

            'iva.required' => 'Debe indicar si el producto aplica IVA.',
            'iva.boolean' => 'El valor para IVA no es válido.',
            'psychotropic.required' => 'Debe indicar si el producto es psicotrópico.',
            'psychotropic.boolean' => 'El valor para Psicotrópico no es válido.',
            'is_colombian_origin.required' => 'Debe indicar si el producto es de Plan Colombia.',
            'is_colombian_origin.boolean' => 'El valor para P.Colombia no es válido.',

            'photo_url.required' => 'La imagen del producto es obligatoria.',
            'photo_url.image' => 'El archivo debe ser una imagen válida.',
            'photo_url.mimes' => 'La imagen debe ser de tipo: jpeg, png, jpg, gif, svg o webp.',
            'photo_url.max' => 'La imagen no debe pesar más de 2MB.',

            'related_product_ids.array' => 'Los productos relacionados deben ser una lista.',
            'related_product_ids.*.exists' => 'Uno de los productos alternativos seleccionados no es válido.',
        ];
    }
}
