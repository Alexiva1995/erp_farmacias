<?php

declare(strict_types=1);

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeClientsImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clients_file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ];
    }

    public function messages(): array
    {
        return [
            'clients_file.required' => 'El archivo de listado de clientes es obligatorio.',
            'clients_file.file'     => 'El archivo de clientes debe ser un archivo válido.',
            'clients_file.mimes'    => 'El formato debe ser .xlsx, .xls, .csv o .txt.',
        ];
    }
}
