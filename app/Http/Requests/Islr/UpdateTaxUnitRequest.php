<?php

namespace App\Http\Requests\Islr;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'value' => 'required|numeric|min:0',
            'effective_date' => 'nullable|date',
            'notes' => 'nullable|string|max:500'
        ];
    }
}
