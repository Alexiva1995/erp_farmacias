<?php

namespace App\Http\Requests\IaAssistant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBarcodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'barcode' => 'required|string|max:255',
            'force' => 'nullable|boolean',
        ];
    }
}
