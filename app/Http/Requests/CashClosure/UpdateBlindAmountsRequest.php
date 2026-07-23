<?php

namespace App\Http\Requests\CashClosure;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBlindAmountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role_id === 1;
    }

    public function rules(): array
    {
        return [
            'id' => 'required|exists:cash_closing,id',
            'declared_cop' => 'nullable|numeric',
            'declared_cop_transfer' => 'nullable|numeric',
            'declared_usd' => 'nullable|numeric',
            'declared_credit' => 'nullable|numeric',
            'declared_bs_mobile' => 'nullable|numeric',
            'declared_bs_card' => 'nullable|numeric',
        ];
    }
}
