<?php

namespace App\Http\Requests\CashClosure;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashClosureRequest extends FormRequest
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
            'id' => 'required',
            'total_cop' => 'required',
            'sobrante_en_peso' => 'required',
            'entregar_efectivo_cop' => 'required',
            'ticket_html' => 'required|string',
            'history_html' => 'required|string',
            'is_blind' => 'nullable|boolean',
            'declared_cop' => 'nullable|numeric',
            'declared_cop_transfer' => 'nullable|numeric',
            'declared_usd' => 'nullable|numeric',
            'declared_credit' => 'nullable|numeric',
            'declared_bs_mobile' => 'nullable|numeric',
            'declared_bs_card' => 'nullable|numeric',
        ];
    }
}
