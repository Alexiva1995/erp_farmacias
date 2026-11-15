<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaasPaymentRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para registrar un pago y renovar licencia.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['nullable', 'string', 'max:100'],
            'saas_subscription_id' => ['nullable', 'integer'],
            'plan_code' => ['nullable', 'string', 'max:50'],
            'months_to_add' => ['nullable', 'integer', 'min:1', 'max:120'],
            'amount_usd' => ['required', 'numeric', 'min:0'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.0001'],
            'amount_paid_currency' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'in:USD,BS,COP,EUR'],
            'payment_method' => ['required', 'string', 'max:50'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'auto_approve' => ['nullable', 'boolean'],
        ];
    }
}
