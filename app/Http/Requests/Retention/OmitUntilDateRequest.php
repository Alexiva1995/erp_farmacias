<?php

namespace App\Http\Requests\Retention;

use Illuminate\Foundation\Http\FormRequest;

class OmitUntilDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cutoff_date' => 'required|date',
        ];
    }

    public function messages(): array
    {
        return [
            'cutoff_date.required' => 'La fecha límite es obligatoria.',
            'cutoff_date.date' => 'La fecha límite debe ser una fecha válida.',
        ];
    }
}
