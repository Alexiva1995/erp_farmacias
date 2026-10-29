<?php

declare(strict_types=1);

namespace App\Http\Requests\Cashea;

use Illuminate\Foundation\Http\FormRequest;

class PayInstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount_usd' => ['nullable', 'numeric', 'min:0.01'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
