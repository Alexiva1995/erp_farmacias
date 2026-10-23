<?php

declare(strict_types=1);

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class StoreModifierGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'min_selections' => ['required', 'integer', 'min:0'],
            'max_selections' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'options' => ['nullable', 'array'],
            'options.*.name' => ['required', 'string', 'max:255'],
            'options.*.modifier_type' => ['required', 'string', 'in:add,remove,info'],
            'options.*.extra_price_usd' => ['nullable', 'numeric', 'min:0'],
            'options.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'options.*.portion' => ['nullable', 'numeric', 'min:0'],
            'options.*.sort_order' => ['nullable', 'integer'],
            'options.*.is_active' => ['nullable', 'boolean'],
        ];
    }
}
