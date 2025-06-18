<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // Aquí puedes poner lógica de autorización más compleja si es necesario.
        // Por ahora, solo verificamos que el usuario esté autenticado.
        return true;
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
            'active_ingredient' => 'nullable|string|max:255',
            'laboratory_id' => 'nullable|integer|exists:laboratories,id',
            'sale_price' => 'nullable|numeric|min:0',
            'origin_id' => 'nullable|integer|exists:origins,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'barcode' => ['nullable', 'string', 'max:255', 'unique:products,barcode,' . $productId],
            'psychotropic' => 'sometimes|boolean',
            'from_colombia' => 'sometimes|boolean',
            'related_product_ids' => 'nullable|array',
            'related_product_ids.*' => 'integer|exists:products,id',
        ];
    }
}
