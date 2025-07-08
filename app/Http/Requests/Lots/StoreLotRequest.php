<?php

namespace App\Http\Requests\Lots;

use Illuminate\Foundation\Http\FormRequest;

class StoreLotRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:0',
            'expiration_date' => 'required|date',
            'lot_number' => 'nullable|string|max:255',
            'unit_cost' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:255',
            'supplier_id' => 'nullable|exists:suppliers,id',
        ];
    }
}
