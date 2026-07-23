<?php

namespace App\Http\Requests\Finances;

use Illuminate\Foundation\Http\FormRequest;

class AcceptMismatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role_id, [1, 2]);
    }

    public function rules(): array
    {
        return [
            'cash_closing_id' => 'required|exists:cash_closing,id',
            'currency' => 'required|in:USD,COP,BS',
            'mismatch_type' => 'required|string',
            'difference' => 'required|numeric',
        ];
    }
}
