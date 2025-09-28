<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeVouchersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vouchers' => 'required|array|min:1',
            'vouchers.*.id' => 'required|integer|min:1',
            'vouchers.*.amount_usd' => 'required|numeric|min:0.01',
        ];
    }

    public function messages(): array
    {
        return [
            'vouchers.required' => 'Debe enviar al menos un bono o deducción.',
            'vouchers.min' => 'Debe enviar al menos un bono o deducción.',
            'vouchers.*.id.required' => 'El id del bono o deducción es obligatorio.',
            'vouchers.*.id.integer' => 'El id debe ser un número entero.',
            'vouchers.*.amount_usd.required' => 'El monto es obligatorio.',
            'vouchers.*.amount_usd.numeric' => 'El monto debe ser un número.',
            'vouchers.*.amount_usd.min' => 'El monto debe ser mayor que 0.',
        ];
    }
}
