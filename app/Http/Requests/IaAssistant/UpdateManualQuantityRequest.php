<?php

namespace App\Http\Requests\IaAssistant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateManualQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => 'nullable|numeric|min:-999999|max:999999',
        ];
    }
}
