<?php

namespace App\Http\Requests\Lots;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLotRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'supplier_id' => 'nullable|exists:suppliers,id',
            'quantity' => 'required|integer|min:0',
            'lot_number' => 'nullable|string|max:255',
            'expiration_date' => 'required|date',
            'location' => 'nullable|string|max:255',
            'unit_cost' => 'required|numeric|min:0',
        ];
    }
}
