<?php

namespace App\Http\Requests\Lots;

use Illuminate\Foundation\Http\FormRequest;

class BatchUpdateLotRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'product_id' => 'required|integer|exists:products,id',
            'lots' => 'required|array|min:1',
            'lots.*.id' => 'nullable|integer',
            'lots.*.lot_number' => 'required|string|max:255',
            'lots.*.quantity' => 'required|integer|min:0',
            'lots.*.expiration_date' => 'required|date',
            'lots.*.unit_cost' => 'nullable|numeric|min:0',
            'lots.*.location' => 'nullable|string|max:255',
        ];
    }
}
