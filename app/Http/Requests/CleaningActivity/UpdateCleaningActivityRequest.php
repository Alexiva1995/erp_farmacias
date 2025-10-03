<?php

namespace App\Http\Requests\CleaningActivity;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCleaningActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'activity' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'frequency' => 'sometimes|required|in:Diaria,Semanal,Bimestral,Mensual,Trimestral,Semestral,Anual',
        ];
    }

    public function messages(): array
    {
        return [
            'activity.required' => 'El nombre de la actividad es obligatorio.',
            'activity.max' => 'El nombre de la actividad no puede exceder 255 caracteres.',
            'frequency.required' => 'La frecuencia es obligatoria.',
            'frequency.in' => 'La frecuencia seleccionada no es válida.',
        ];
    }
}
