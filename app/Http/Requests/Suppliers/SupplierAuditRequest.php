<?php

declare(strict_types=1);

namespace App\Http\Requests\Suppliers;

use Illuminate\Foundation\Http\FormRequest;

class SupplierAuditRequest extends FormRequest
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
            'supplier_id' => ['nullable', 'integer'],
            'month'       => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ];
    }

    /**
     * Mensajes de error personalizados en español
     */
    public function messages(): array
    {
        return [
            'supplier_id.integer' => 'El identificador del proveedor debe ser un número entero.',
            'month.regex'         => 'El mes de análisis debe tener el formato AAAA-MM (ej. 2026-10).',
        ];
    }
}
