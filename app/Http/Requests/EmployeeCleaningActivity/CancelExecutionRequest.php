<?php

namespace App\Http\Requests\EmployeeCleaningActivity;

use Illuminate\Foundation\Http\FormRequest;

class CancelExecutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => 'required|string|max:500',
        ];
    }
}
