<?php

namespace App\Http\Requests\EmployeeLaboratory;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeLaboratoryRequest extends FormRequest
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
            'employee_id' => 'required|exists:employees,id',
            'laboratory_ids' => 'required|array|min:1',
            'laboratory_ids.*' => 'exists:laboratories,id',
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
            'employee_id.required' => 'Debe seleccionar un empleado',
            'employee_id.exists' => 'El empleado seleccionado no existe',
            'laboratory_ids.required' => 'Debe seleccionar al menos un laboratorio',
            'laboratory_ids.array' => 'Los laboratorios deben ser un array',
            'laboratory_ids.min' => 'Debe seleccionar al menos un laboratorio',
            'laboratory_ids.*.exists' => 'Uno o más laboratorios seleccionados no existen',
        ];
    }
}
