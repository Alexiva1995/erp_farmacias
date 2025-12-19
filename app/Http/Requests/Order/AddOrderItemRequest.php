<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class AddOrderItemRequest extends FormRequest
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
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'price_at_product' => ['required', 'numeric', 'min:0'],
            'currency_at_order' => ['required', 'string', 'in:USD,BS,COP'],
            'price_usd_unit' => ['required', 'numeric', 'min:0'],
            'pack_id' => ['nullable', 'integer', 'exists:product_packs,id'],
        ];
    }


    public function messages(): array
    {
        return [
            'product_id.required' => 'El prodcuto es obligatorio',
            'quantity.required' => 'El cantidad es obligatorio',
            'price_at_product.required' => 'El precio del producto es obligatorio',
            'currency_at_order.required' => 'El moneda es obligatorio',
        ];
    }
}
