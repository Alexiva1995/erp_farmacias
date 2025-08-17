<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierLaboratoryRequest extends FormRequest
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
            'laboratory_id' => ['required', 'exists:laboratories,id'],
            'phone' => ['string', 'max:100', 'regex:/^\+?\d{7,15}$/']
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
            'laboratory_id.required' => 'Debe seleccionar un laboratorio.',
            'laboratory_id.exists' => 'El laboratorio seleccionado no es válido.',
            
            'phone.string' => 'El teléfono debe ser texto.',
            'phone.max' => 'El teléfono no puede exceder los 100 caracteres.',
            'phone.regex' => 'El teléfono debe contener solo números.'
        ];
    }
}
