<?php

namespace App\Http\Requests\EmployeeCleaningActivity;

use Illuminate\Foundation\Http\FormRequest;

class ApproveExecutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => 'nullable|string|max:500',
        ];
    }
}
