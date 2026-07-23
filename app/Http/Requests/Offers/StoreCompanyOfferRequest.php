<?php

namespace App\Http\Requests\Offers;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'required|exists:companies,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_active' => 'required|boolean',
            'scales' => 'required|array|min:1',
            'scales.*.min_amount' => 'required|numeric|min:0',
            'scales.*.max_amount' => 'required|numeric|min:0|gt:scales.*.min_amount',
            'scales.*.discount_percentage' => 'required|numeric|min:0|max:100',
        ];
    }
}
