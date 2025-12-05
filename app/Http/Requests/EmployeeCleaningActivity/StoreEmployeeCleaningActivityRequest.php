<?php

namespace App\Http\Requests\EmployeeCleaningActivity;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeCleaningActivityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => 'required|integer|exists:employees,id',
            'activities' => 'required|array|min:1',
            'activities.*.activity_id' => 'required|integer|exists:cleaning_activities,id|distinct',
            'activities.*.status' => 'required|in:Pendiente,Completada,Cancelada',
            'activities.*.assigned_date' => 'nullable|date',
            'activities.*.completed_date' => 'nullable|date|after_or_equal:activities.*.assigned_date',
            'activities.*.notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'El empleado es requerido.',
            'employee_id.exists' => 'El empleado seleccionado no existe.',
            'activities.required' => 'Debe asignar al menos una actividad.',
            'activities.array' => 'Las actividades deben ser un arreglo.',
            'activities.min' => 'Debe asignar al menos una actividad.',
            'activities.*.activity_id.required' => 'El ID de la actividad es requerido.',
            'activities.*.activity_id.exists' => 'La actividad seleccionada no existe.',
            'activities.*.activity_id.distinct' => 'No puede asignar la misma actividad más de una vez.',
            'activities.*.status.required' => 'El estado de la actividad es requerido.',
            'activities.*.status.in' => 'El estado debe ser: Pendiente, Completada o Cancelada.',
            'activities.*.assigned_date.date' => 'La fecha de asignación debe ser una fecha válida.',
            'activities.*.completed_date.date' => 'La fecha de completado debe ser una fecha válida.',
            'activities.*.completed_date.after_or_equal' => 'La fecha de completado debe ser posterior o igual a la fecha de asignación.',
            'activities.*.notes.string' => 'Las notas deben ser texto.',
            'activities.*.notes.max' => 'Las notas no pueden exceder 500 caracteres.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Asegurar que employee_id sea un entero
        if ($this->has('employee_id')) {
            $this->merge([
                'employee_id' => (int) $this->employee_id,
            ]);
        }
    }
}
