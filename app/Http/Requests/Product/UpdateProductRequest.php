<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
     * 
     *
     * 
     */
    protected function prepareForValidation()
    {
        if ($this->has('photo_url') && is_string($this->input('photo_url'))) {
            $this->request->remove('photo_url');
        }

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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $productId = $this->route('product')->id ?? null;

        return [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'active_ingredient' => 'nullable|string|max:255',
            'laboratory_id' => 'nullable|integer|exists:laboratories,id',
            'unit_cost' => 'nullable|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'origin_id' => 'nullable|integer|exists:origins,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'barcode' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($productId) {
                    if (empty($value)) {
                        return;
                    }

                    // Buscar si otro producto tiene este código de barras
                    $existingProduct = \App\Models\Product::withoutGlobalScope('not_deleted')
                        ->withTrashed()
                        ->with('laboratory')
                        ->where('barcode', $value)
                        ->where('id', '!=', $productId)
                        ->first();

                    if ($existingProduct) {
                        // Si el producto existente está eliminado, permitimos la validación para que se fusione automáticamente
                        if ($existingProduct->is_deleted || $existingProduct->trashed()) {
                            return;
                        }

                        // Si está activo, informamos ID, nombre y laboratorio
                        $labName = $existingProduct->laboratory?->name ?? 'Sin Laboratorio';
                        $fail("El código de barras '{$value}' ya está asignado al producto ID #{$existingProduct->id} - {$existingProduct->name} (Lab: {$labName}).");
                    }
                },
            ],
            'psychotropic' => 'sometimes|boolean',
            'iva' => 'sometimes|boolean',
            'is_colombian_origin' => 'sometimes|boolean',
            'is_novaventa' => 'sometimes|boolean',
            'is_scarce' => 'sometimes|boolean',
            'is_unified_group' => 'sometimes|boolean',
            'no_pvp' => 'sometimes|boolean',
            'group_id' => 'nullable|integer|exists:groups_products,id',
            'photo_url' => [
                'sometimes',
                'nullable',
                'image',
                'max:2048',
            ],
            'presentation' => 'nullable|numeric|min:0',
            'unit_of_measure' => 'nullable|string|in:g,ml,und',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'supplier_ids' => 'sometimes|array',
            'supplier_ids.*' => 'integer|exists:suppliers,id',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422)
        );
    }
}
