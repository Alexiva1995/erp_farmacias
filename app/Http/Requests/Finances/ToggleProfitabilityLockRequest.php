<?php

namespace App\Http\Requests\Finances;

use Illuminate\Foundation\Http\FormRequest;

class ToggleProfitabilityLockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'profitability_percentage' => 'nullable|numeric',
        ];
    }
}
