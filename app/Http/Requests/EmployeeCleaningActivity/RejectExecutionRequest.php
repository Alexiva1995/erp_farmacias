<?php

namespace App\Http\Requests\EmployeeCleaningActivity;

use Illuminate\Foundation\Http\FormRequest;

class RejectExecutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => 'required|string|max:500',
        ];
    }
}
