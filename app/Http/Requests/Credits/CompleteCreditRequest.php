<?php

namespace App\Http\Requests\Credits;

use Illuminate\Foundation\Http\FormRequest;

class CompleteCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clientId' => ['required', 'integer', 'exists:clients,id'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.currency' => ['nullable', 'string'],
            'payments.*.reference' => ['nullable', 'string'],
            'changeAmount' => ['nullable', 'numeric', 'min:0'],
            'changeAmountUSD' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
