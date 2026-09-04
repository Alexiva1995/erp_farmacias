<?php

declare(strict_types=1);

namespace App\Http\Requests\Configuration;

use Illuminate\Foundation\Http\FormRequest;

class ImportExternalCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'            => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
            'cutoff_date'     => ['nullable', 'date'],
            'is_initial_load' => ['nullable', 'boolean'],
        ];
    }
}
