<?php

namespace App\Http\Requests\PendingPayments;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_date' => 'required|date',
        ];
    }
}
