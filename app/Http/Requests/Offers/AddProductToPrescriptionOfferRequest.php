<?php

namespace App\Http\Requests\Offers;

use Illuminate\Foundation\Http\FormRequest;

class AddProductToPrescriptionOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'sale_price' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
        ];
    }
}
