<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class SyncContingencyOrderRequest extends FormRequest
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
            'uuid'         => ['required', 'string'],
            'items'        => ['required', 'array', 'min:1'],
            'items.*.id'   => ['required', 'numeric'],
            'items.*.quantity' => ['required', 'numeric', 'min:1'],
            'total_amount' => ['required', 'numeric'],
            'total_usd'    => ['nullable', 'numeric'],
            'currency'     => ['nullable', 'string', 'in:COP,USD,BS'],
            'payments'     => ['nullable', 'array'],
            'change_amount' => ['nullable', 'numeric'],
            'change_amount_cop' => ['nullable', 'numeric'],
            'change_amount_usd' => ['nullable', 'numeric'],
        ];
    }
}
