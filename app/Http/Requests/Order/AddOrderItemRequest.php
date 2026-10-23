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
            'order_detail_id' => ['nullable', 'integer', 'exists:order_details,id'],
            'product_id' => ['nullable', 'required_without_all:dish_id,court_id', 'exists:products,id'],
            'dish_id' => ['nullable', 'required_without_all:product_id,court_id', 'exists:dishes,id'],
            'court_id' => ['nullable', 'required_without_all:product_id,dish_id', 'exists:courts,id'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'price_at_product' => ['required', 'numeric', 'min:0'],
            'currency_at_order' => ['required', 'string', 'in:USD,BS,COP'],
            'price_usd_unit' => ['required', 'numeric', 'min:0'],
            'pack_id' => ['nullable', 'integer', 'exists:product_packs,id'],
            'notes' => ['nullable', 'string'],
            'modifiers' => ['nullable', 'array'],
            'modifiers.*.modifier_option_id' => ['nullable', 'integer', 'exists:modifier_options,id'],
            'modifiers.*.name' => ['nullable', 'string'],
            'modifiers.*.modifier_type' => ['nullable', 'string', 'in:add,remove,info'],
            'modifiers.*.extra_price_usd' => ['nullable', 'numeric'],
            'modifiers.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'modifiers.*.portion' => ['nullable', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'El producto es obligatorio',
            'quantity.required' => 'La cantidad es obligatoria',
            'price_at_product.required' => 'El precio del producto es obligatorio',
            'currency_at_order.required' => 'La moneda es obligatoria',
        ];
    }
}
