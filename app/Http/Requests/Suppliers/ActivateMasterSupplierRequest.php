<?php

declare(strict_types=1);

namespace App\Http\Requests\Suppliers;

use Illuminate\Foundation\Http\FormRequest;

class ActivateMasterSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para activar un proveedor del catálogo maestro.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'master_supplier_id' => ['required', 'integer', 'min:1'],
            'sender_email' => ['nullable', 'string', 'email', 'max:255'],
            'credit_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'master_supplier_id.required' => 'El identificador del proveedor maestro es obligatorio.',
            'master_supplier_id.integer' => 'El identificador del proveedor debe ser un número entero válido.',
            'sender_email.email' => 'El correo electrónico del remitente debe tener un formato válido.',
            'credit_days.integer' => 'Los días de crédito deben ser un número entero.',
            'credit_days.min' => 'Los días de crédito no pueden ser negativos.',
        ];
    }
}
