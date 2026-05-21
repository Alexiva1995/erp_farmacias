<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class DashboardStatsRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date'   => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }

    /**
     * Obtiene los mensajes de error personalizados para las reglas definidas.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.required' => 'La fecha de inicio es requerida.',
            'start_date.date' => 'La fecha de inicio debe ser una fecha válida.',
            'start_date.date_format' => 'La fecha de inicio debe tener el formato YYYY-MM-DD.',
            'end_date.required' => 'La fecha de fin es requerida.',
            'end_date.date' => 'La fecha de fin debe ser una fecha válida.',
            'end_date.date_format' => 'La fecha de fin debe tener el formato YYYY-MM-DD.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser mayor o igual a la fecha de inicio.',
        ];
    }
}
