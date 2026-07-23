<?php

namespace App\Http\Requests\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

class EcommerceCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name'            => 'required|string|max:255',
            'customer_email'           => 'nullable|email|max:255',
            'customer_phone'           => 'required|string|max:30',
            'shipping_address'         => 'nullable|string|max:500',
            'notes'                    => 'nullable|string|max:1000',
            'payment_method'           => 'nullable|string|max:50',
            'payment_currency'         => 'nullable|string|max:10',
            'customer_document_type'   => 'nullable|string|max:5',
            'customer_document_number' => 'nullable|string|max:50',
            'payment_proof'            => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => 'required|integer',
            'items.*.variant_id'       => 'nullable|integer',
            'items.*.quantity'         => 'required|integer|min:1',
        ];
    }
}
