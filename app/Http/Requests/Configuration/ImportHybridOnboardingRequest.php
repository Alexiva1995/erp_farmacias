<?php

declare(strict_types=1);

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

class ImportHybridOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'products_file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
            'lots_file'     => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
            'sync_master'   => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'products_file.required' => 'El archivo general de productos es obligatorio.',
            'products_file.file'     => 'El archivo de productos debe ser un archivo válido.',
            'products_file.mimes'    => 'El archivo de productos debe tener extensión .xls, .xlsx o .csv.',
            'lots_file.required'     => 'El archivo detallado de lotes es obligatorio.',
            'lots_file.file'         => 'El archivo de lotes debe ser un archivo válido.',
            'lots_file.mimes'        => 'El archivo de lotes debe tener extensión .xls, .xlsx o .csv.',
        ];
    }
}
