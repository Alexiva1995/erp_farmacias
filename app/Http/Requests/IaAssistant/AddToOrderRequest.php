<?php

namespace App\Http\Requests\IaAssistant;

use Illuminate\Foundation\Http\FormRequest;

class AddToOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0.01',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'product_supplier_id' => 'nullable|exists:product_suppliers,id',
            'unit_cost' => 'nullable|numeric|min:0',
        ];
    }
}
