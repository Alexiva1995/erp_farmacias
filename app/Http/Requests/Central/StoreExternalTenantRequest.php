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
            'plan' => ['required', 'string', 'max:50'],
            'billing_cycle' => ['nullable', 'string', 'in:monthly,quarterly,annual,lifetime'],
            'status' => ['nullable', 'string', 'in:trial,active,past_due,suspended,cancelled'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'billing_date' => ['required', 'date'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'seller_name' => ['nullable', 'string', 'max:100'],
            'max_users' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'max_branches' => ['nullable', 'integer', 'min:1', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
