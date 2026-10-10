<?php

declare(strict_types=1);

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeSalesOrdersImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sales_file'    => ['nullable', 'file', 'mimes:xlsx,xls,csv,txt'],
            'sales_files'   => ['nullable', 'array'],
            'sales_files.*' => ['file', 'mimes:xlsx,xls,csv,txt'],
        ];
    }

    public function messages(): array
    {
        return [
            'sales_file.file'      => 'El archivo de ventas debe ser un archivo válido.',
            'sales_file.mimes'     => 'El formato debe ser .xlsx, .xls, .csv o .txt.',
            'sales_files.*.file'   => 'Cada archivo de ventas debe ser válido.',
            'sales_files.*.mimes'  => 'Cada archivo debe tener formato .xlsx, .xls, .csv o .txt.',
        ];
    }
}
