<?php

namespace App\Http\Requests\EmployeeCleaningActivity;

use Illuminate\Foundation\Http\FormRequest;

class UpdateActivityStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:Pendiente,Completada,Cancelada',
            'completed_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
