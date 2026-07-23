<?php

namespace App\Http\Requests\Offers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductPackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:100',
            'pack_config' => 'sometimes|required|array',
            'total_price' => 'sometimes|required|numeric|min:0',
            'max_quantity' => 'nullable|integer|min:1',
            'max_sale_date' => 'nullable|date|after_or_equal:today',
            'is_active' => 'boolean',
        ];
    }
}
