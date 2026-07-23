<?php

namespace App\Http\Requests\Retention;

use Illuminate\Foundation\Http\FormRequest;

class BulkGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'exists:invoices,id',
        ];
    }
}
