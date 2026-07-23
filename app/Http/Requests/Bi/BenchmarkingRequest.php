<?php

namespace App\Http\Requests\Bi;

use Illuminate\Foundation\Http\FormRequest;

class BenchmarkingRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación aplicables a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lab_a' => ['required', 'integer'],
            'lab_b' => ['required', 'integer'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'group_by_corporate' => ['nullable'],
        ];
    }
}
