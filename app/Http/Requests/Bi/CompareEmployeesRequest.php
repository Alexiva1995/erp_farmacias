<?php

namespace App\Http\Requests\Bi;

use Illuminate\Foundation\Http\FormRequest;

class CompareEmployeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_a' => 'required|integer',
            'employee_b' => 'required|integer',
        ];
    }
}
