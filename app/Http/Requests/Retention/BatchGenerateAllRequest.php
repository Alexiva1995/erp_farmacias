<?php

namespace App\Http\Requests\Retention;

use Illuminate\Foundation\Http\FormRequest;

class BatchGenerateAllRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'retention_date' => 'nullable|date',
        ];
    }
}
