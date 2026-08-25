<?php

namespace App\Http\Requests\Suppliers;

use Illuminate\Foundation\Http\FormRequest;

class SupplierPublicUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
            'file_2' => 'nullable|file|mimes:xlsx,xls,csv|max:20480',
            'files' => 'nullable|array',
            'files.*' => 'nullable|file|mimes:xlsx,xls,csv|max:20480',
            'exchange_rate' => 'required|numeric|min:0.01',
        ];
    }
}
