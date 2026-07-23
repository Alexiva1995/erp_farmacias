<?php

namespace App\Http\Requests\InventoryCycle;

use Illuminate\Foundation\Http\FormRequest;

class ProcessCountActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => 'required|in:approve,reject',
            'corrected_quantity' => 'nullable|numeric',
            'updated_lots' => 'nullable|array',
            'updated_lots.*.id' => 'required_with:updated_lots|integer|exists:product_lots,id',
            'updated_lots.*.quantity' => 'required_with:updated_lots|integer|min:0',
            'new_lots' => 'nullable|array',
            'new_lots.*.lot_number' => 'required_with:new_lots|string|max:255',
            'new_lots.*.expiration_date' => 'required_with:new_lots|date',
            'new_lots.*.quantity' => 'required_with:new_lots|integer|min:0',
        ];
    }
}
