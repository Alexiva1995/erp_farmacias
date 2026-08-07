<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'numeric'],
            'seller_id' => ['required', 'numeric'],
            'currency'  => ['nullable', 'string', 'in:COP,USD,BS'],
        ];
    }


     public function messages(): array
    {
        return [
            'client_id.required' => 'El Cliente debe ser obligatorio',
        ];
    }
}
