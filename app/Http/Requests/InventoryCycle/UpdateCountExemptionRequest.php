<?php

namespace App\Http\Requests\InventoryCycle;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCountExemptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exempt_quantity'  => ['required', 'integer', 'min:0'],
            'exemption_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
