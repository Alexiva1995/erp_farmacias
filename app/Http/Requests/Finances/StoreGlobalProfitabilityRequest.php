<?php

namespace App\Http\Requests\Finances;

use Illuminate\Foundation\Http\FormRequest;

class StoreGlobalProfitabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'default_profitability_percentage' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'packaging_cost' => ['nullable', 'numeric', 'min:0'],
            'expense_margin' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'profit_margin' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'tax_usa' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
        ];
    }
}
