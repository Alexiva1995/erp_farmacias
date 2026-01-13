<?php

namespace App\Http\Requests;

use App\Models\Laboratory;
use Illuminate\Foundation\Http\FormRequest;

class StoreLaboratoryRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                // Validación case-insensitive: verificar que no exista un laboratorio con el mismo nombre (ignorando mayúsculas/minúsculas)
                function ($attribute, $value, $fail) {
                    $exists = Laboratory::whereRaw('LOWER(name) = LOWER(?)', [trim($value)])->exists();
                    if ($exists) {
                        $fail('Ya existe un laboratorio con este nombre.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del laboratorio es obligatorio.',
            'name.string' => 'El nombre del laboratorio debe ser texto.',
            'name.max' => 'El nombre del laboratorio no puede exceder los 255 caracteres.',
        ];
    }
}
