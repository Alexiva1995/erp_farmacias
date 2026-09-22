<?php

namespace App\Http\Requests\Suppliers;

use Illuminate\Foundation\Http\FormRequest;

class MergeSuppliersRequest extends FormRequest
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
            'target_supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'source_supplier_id' => ['required', 'integer', 'exists:suppliers,id', 'different:target_supplier_id'],
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
            'target_supplier_id.required' => 'El proveedor principal es obligatorio.',
            'target_supplier_id.exists' => 'El proveedor principal seleccionado no existe.',
            'source_supplier_id.required' => 'El proveedor a fusionar es obligatorio.',
            'source_supplier_id.exists' => 'El proveedor a fusionar seleccionado no existe.',
            'source_supplier_id.different' => 'No puedes fusionar un proveedor consigo mismo.',
        ];
    }
}
