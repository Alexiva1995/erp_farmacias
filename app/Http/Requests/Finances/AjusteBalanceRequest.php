<?php

namespace App\Http\Requests\Finances;

use Illuminate\Foundation\Http\FormRequest;

class AjusteBalanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->check() && auth()->user()->role_id === 1;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'currency' => 'required|string|in:USD,BS,COP',
            'type' => 'required|string', // El método (ej: CASH, MOBILE)
            'new_balance' => 'required|numeric|min:0',
        ];
    }
}
