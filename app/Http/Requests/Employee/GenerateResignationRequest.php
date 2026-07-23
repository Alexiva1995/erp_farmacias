<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class GenerateResignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|integer',
            'employee_name' => 'required|string',
            'employee_identification' => 'required|string',
            'employee_position' => 'nullable|string',
            'start_date' => 'required|date',
            'resignation_type' => 'required|in:voluntary,unjustified_dismissal',
            'request_date' => 'required_if:is_edit,false|nullable|date',
            'effective_date' => 'required|date',
            'is_edit' => 'nullable|boolean',
        ];
    }
}
