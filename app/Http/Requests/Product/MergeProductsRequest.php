<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class MergeProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id_1' => 'required|integer|exists:products,id',
            'product_id_2' => 'required|integer|exists:products,id',
            'keep_product_id' => 'required|integer|in:' . $this->input('product_id_1') . ',' . $this->input('product_id_2'),
        ];
    }
}
