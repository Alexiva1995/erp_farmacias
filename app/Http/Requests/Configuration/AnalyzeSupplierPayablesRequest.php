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
            'payables_file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ];
    }

    public function messages(): array
    {
        return [
            'payables_file.required' => 'El archivo de Cuentas por Pagar y Proveedores es obligatorio.',
            'payables_file.file'     => 'El archivo de Cuentas por Pagar debe ser un archivo válido.',
            'payables_file.mimes'    => 'El formato debe ser .xlsx, .xls, .csv o .txt.',
        ];
    }
}
