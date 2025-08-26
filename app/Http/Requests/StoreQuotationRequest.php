<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'total_amount_usd' => ['required', 'numeric', 'min:0'],
            'total_iva_usd' => ['required', 'numeric', 'min:0'],
            'grand_total_usd' => ['required', 'numeric', 'min:0'],
            'currency' => ['required'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.id' => ['required', 'exists:products,id'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }


     public function messages(): array
    {
        return [
            'currency.required' => 'El tipo de moneda es obligatorio',
            'products.required' => 'La cotización debe contener al menos.',
            'products.min' => 'La cotización debe contener al menos un producto.',
            'products.*.id.exists' => 'Uno de los productos seleccionados no es válido.',
        ];
    }
}
