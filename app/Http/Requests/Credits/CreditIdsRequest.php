<?php

namespace App\Http\Requests\Credits;

use Illuminate\Foundation\Http\FormRequest;

class CreditIdsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'credit_ids' => 'required|array',
            'credit_ids.*' => 'integer|exists:credits,id',
        ];
    }
}
