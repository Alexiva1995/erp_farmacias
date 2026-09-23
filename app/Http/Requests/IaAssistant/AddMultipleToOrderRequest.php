<?php

namespace App\Http\Requests\IaAssistant;

use Illuminate\Foundation\Http\FormRequest;

class AddMultipleToOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.supplier_id' => 'nullable|integer',
            'items.*.product_supplier_id' => 'nullable|integer',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
        ];
    }
}
