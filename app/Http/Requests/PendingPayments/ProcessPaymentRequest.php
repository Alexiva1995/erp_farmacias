<?php

namespace App\Http\Requests\PendingPayments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_ids' => 'required|array',
            'invoice_ids.*' => 'exists:invoices,id',
            'payment_type' => 'required|in:full,partial',
            'payment_currency' => 'required|in:VES,USD,COP,BS',
            'payment_amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string|in:CASH,TRANSFER,CARD,MOBILE,BINANCE,PAYPAL,CREDIT',
            'reference' => 'nullable|string|max:100',
            'destination_bank' => 'nullable|string|max:150',
            'photo_url' => 'nullable|string',
            'notes' => 'nullable|string|max:500',
            'has_iva' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method.required' => 'El método de pago es necesario',
            'payment_method.in' => 'El método de pago seleccionado no es válido para la moneda seleccionada',
        ];
    }
}
