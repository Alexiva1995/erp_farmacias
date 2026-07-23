<?php

namespace App\Http\Requests\Islr;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIslrDeclarationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => 'nullable|integer|min:2000|max:' . (now()->year + 1),
            'amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:paid,unpaid',
            'declaration_date' => 'nullable|date',
        ];
    }
}
