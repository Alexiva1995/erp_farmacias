<?php

namespace App\Http\Requests\Credits;

use Illuminate\Foundation\Http\FormRequest;

class GetPaymentHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id' => 'required|integer|exists:clients,id',
        ];
    }
}
