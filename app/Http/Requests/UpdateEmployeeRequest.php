<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
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
        $employee_id = $this->employee->id;
        $user_id = $this->employee->user->id;

        return [
            'name' => 'required|string|min:3|max:40',
            'last_name' => 'required|string|min:3|max:40',
            'identification' => [
                'required',
                'digits_between:6,8',
                Rule::unique('employees', 'identification')->ignore($employee_id),
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($user_id),
            ],
            'password' => 'nullable|string|min:3',
            'role' => 'required|integer|exists:roles,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.string' => 'El nombre debe ser texto.',
            'name.min' => 'El nombre debe tener al menos 3 caracteres.',
            'name.max' => 'El nombre no puede exceder 40 caracteres.',
            'last_name.required' => 'El apellido es obligatorio.',
            'last_name.string' => 'El apellido debe ser texto.',
            'last_name.min' => 'El apellido debe tener al menos 3 caracteres.',
            'last_name.max' => 'El apellido no puede exceder 40 caracteres.',
            'identification.required' => 'La identificación es obligatoria.',
            'identification.digits_between' => 'La identificación debe tener entre 6 y 8 dígitos numéricos.',
            'identification.unique' => 'Esta identificación ya está registrada.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Debe proporcionar un correo electrónico válido.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'password.string' => 'La contraseña debe ser texto.',
            'password.min' => 'La contraseña debe tener al menos 3 caracteres.',
            'role.required' => 'El rol es obligatorio.',
            'role.integer' => 'Debe seleccionar un rol.',
            'role.exists' => 'El rol seleccionado no existe en el sistema.',
        ];
    }
}
