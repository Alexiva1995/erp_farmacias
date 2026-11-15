<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\SaasBillingCycleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaasPlanRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para crear o actualizar un plan SaaS.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $planId = $this->route('id');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('saas_plans', 'code')->ignore($planId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'billing_cycle' => ['required', 'string', Rule::in(SaasBillingCycleEnum::values())],
            'price_usd' => ['required', 'numeric', 'min:0'],
            'setup_fee_usd' => ['nullable', 'numeric', 'min:0'],
            'trial_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'grace_days' => ['nullable', 'integer', 'min:0', 'max:90'],
            'max_users' => ['required', 'integer', 'min:1', 'max:10000'],
            'max_branches' => ['required', 'integer', 'min:1', 'max:500'],
            'included_modules' => ['nullable', 'array'],
            'features' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
