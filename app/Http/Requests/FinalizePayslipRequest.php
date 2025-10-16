<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinalizePayslipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency' => 'required|in:USD,BS,COP',
            'count' => 'required|in:Efectivo,Tarjeta,Pago móvil,Transferencia,Binance,Paypal',
            'payed' => 'required|decimal:0,2|min:1.0',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $currency = $this->input('currency');
            $count = $this->input('count');

            $allowedCountsByCurrency = [
                'USD' => ['Efectivo', 'Binance', 'Paypal'],
                'COP' => ['Efectivo', 'Transferencia'],
                'BS' => ['Efectivo', 'Tarjeta', 'Pago móvil', 'Transferencia'],
            ];

            if (
                !isset($allowedCountsByCurrency[$currency]) ||
                !in_array($count, $allowedCountsByCurrency[$currency])
            ) {
                $validator->errors()->add(
                    'count',
                    "El método de pago '{$count}' no está permitido para la moneda {$currency}."
                );
            }
        });
    }

    public function messages()
    {
        return [
            'currency.required' => 'La moneda es necesaria',
            'currency.in' => 'Debe seleccionar una opción válida de moneda',
            'count.required' => 'La cuenta es necesaria',
            'count.in' => 'Debe seleccionar una opción válida de cuenta',
            'payed.required' => 'El monto a pagar es necesario',
            'payed.decimal' => 'El monto debe ser un número válido con hasta 2 decimales',
            'payed.min' => 'El monto debe ser al menos 1',
        ];
    }
}
