<?php

namespace App\Http\Requests\Offers;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpirationOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'months_to_expiration' => 'required|integer|min:1|max:36',
            'discount_percentage' => 'required|numeric|min:0.01|max:100',
            'is_active' => 'boolean',
        ];
    }
}
