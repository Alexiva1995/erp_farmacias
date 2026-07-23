<?php

namespace App\Http\Requests\Resignation;

use Illuminate\Foundation\Http\FormRequest;

class ToggleEmployeeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|integer|exists:employees,id',
            'is_active' => 'required|boolean',
        ];
    }
}
