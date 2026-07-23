<?php

namespace App\Http\Requests\EmployeeCleaningActivity;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMyActivityStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:Pendiente,Procesada',
            'photo' => 'required_if:status,Procesada|image|mimes:jpeg,png,jpg|max:5120',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
