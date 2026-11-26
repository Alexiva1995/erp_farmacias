<?php

namespace App\Http\Requests\Suppliers;

use Illuminate\Foundation\Http\FormRequest;

class PropagateSupplierMergeRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta petición.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para propagar una fusión de proveedor hacia los tenants.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'source_supplier_id' => ['required', 'integer', 'min:1', 'different:target_supplier_id'],
        ];
    }

    /**
     * Mensajes de validación personalizados.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'target_supplier_id.required' => 'El proveedor principal en Master es obligatorio.',
            'target_supplier_id.exists' => 'El proveedor principal seleccionado no existe en el Master.',
            'source_supplier_id.required' => 'El ID del proveedor duplicado es obligatorio.',
            'source_supplier_id.integer' => 'El ID del proveedor duplicado debe ser numérico.',
            'source_supplier_id.min' => 'El ID del proveedor duplicado debe ser mayor a cero.',
            'source_supplier_id.different' => 'El ID duplicado no puede ser igual al proveedor principal.',
        ];
    }
}
