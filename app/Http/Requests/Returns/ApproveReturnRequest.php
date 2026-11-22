<?php

declare(strict_types=1);

namespace App\Http\Requests\Returns;

use Illuminate\Foundation\Http\FormRequest;

class ApproveReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'restock_inventory' => 'nullable|boolean',
            'updated_lots' => 'nullable|array',
            'updated_lots.*.id' => 'required_with:updated_lots|integer|exists:product_lots,id',
            'updated_lots.*.quantity' => 'required_with:updated_lots|integer|min:0',
            'new_lots' => 'nullable|array',
            'new_lots.*.quantity' => 'required_with:new_lots|integer|min:1',
            'new_lots.*.lot_number' => 'nullable|string|max:100',
            'new_lots.*.expiration_date' => 'nullable|date',
            'new_lots.*.location' => 'nullable|string|max:100',
        ];
    }
}
