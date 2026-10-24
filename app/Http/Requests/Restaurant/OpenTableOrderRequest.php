<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class OpenTableOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guests_count' => 'nullable|integer|min:1|max:50',
            'client_id' => 'nullable|exists:clients,id',
        ];
    }
}
