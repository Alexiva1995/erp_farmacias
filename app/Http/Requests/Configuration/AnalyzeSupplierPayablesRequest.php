<?php

declare(strict_types=1);

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeSupplierPayablesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'suppliers_file' => ['nullable', 'file', 'mimes:xlsx,xls,csv,txt'],
            'payables_file'  => ['nullable', 'file', 'mimes:xlsx,xls,csv,txt'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$this->hasFile('suppliers_file') && !$this->hasFile('payables_file')) {
                $validator->errors()->add('files', 'Debe seleccionar al menos el Listado de Proveedores o la Relación de Cuentas por Pagar.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'suppliers_file.file'  => 'El archivo de listado de proveedores debe ser un archivo válido.',
            'suppliers_file.mimes' => 'El formato del listado de proveedores debe ser .xlsx, .xls, .csv o .txt.',
            'payables_file.file'   => 'El archivo de cuentas por pagar debe ser un archivo válido.',
            'payables_file.mimes'  => 'El formato de cuentas por pagar debe ser .xlsx, .xls, .csv o .txt.',
        ];
    }
}
