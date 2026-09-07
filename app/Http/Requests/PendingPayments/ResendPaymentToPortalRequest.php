<?php

declare(strict_types=1);

namespace App\Http\Requests\PendingPayments;

use Illuminate\Foundation\Http\FormRequest;

class ResendPaymentToPortalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_id' => 'required|exists:invoice_payments,id',
            'destination_bank' => 'nullable|string|max:150',
            'reference' => 'nullable|string|max:100',
            'id_type' => 'nullable|string|in:V,E,J,G,v,e,j,g',
            'id_number' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'payment_id.required' => 'El identificador del pago es obligatorio.',
            'payment_id.exists' => 'El pago especificado no existe.',
        ];
    }
}
