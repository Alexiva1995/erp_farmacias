<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use Illuminate\Foundation\Http\FormRequest;

class StoreExternalTenantRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para registrar un tenant externo.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:100'],
            'tenant_id' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:tenants,id'],
            'domain' => ['required', 'string', 'max:150'],
            'vertical' => ['nullable', 'string', 'max:50'],
            'plan' => ['required', 'string', 'in:basic,pro'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'billing_date' => ['required', 'date'],
        ];
    }
}
