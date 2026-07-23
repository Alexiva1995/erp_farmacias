<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class RejectAiMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'          => 'required|integer|exists:products,id',
            'product_supplier_id' => 'required|integer|exists:product_suppliers,id',
            'reason'              => 'nullable|string|max:255',
        ];
    }
}
