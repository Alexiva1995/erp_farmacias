<?php

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

class ImportCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string|in:clientes,proveedores,productos,inventariolot,gastos,cierres',
            'file' => 'required|file|mimes:csv,txt',
        ];
    }
}
