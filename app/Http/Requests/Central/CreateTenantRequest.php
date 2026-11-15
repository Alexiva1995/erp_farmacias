<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\BusinessVerticalEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTenantRequest extends FormRequest
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
        $customKeys = [];
        try {
            $customPresets = app(\App\Contracts\VerticalPresetServiceInterface::class)->getCustomPresets();
            $customKeys = array_keys($customPresets);
        } catch (\Throwable) {
            $customKeys = [];
        }

        $allowedVerticals = array_unique(array_merge(BusinessVerticalEnum::values(), $customKeys));

        return [
            'tenant_id' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-z0-9_-]+$/',
            ],
            'company_name' => ['required', 'string', 'max:255'],
            'admin_name' => ['nullable', 'string', 'max:100'],
            'admin_email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'domain' => ['nullable', 'string', 'max:100'],
            'vertical' => [
                'nullable',
                'string',
                Rule::in($allowedVerticals),
            ],
            'plan' => ['nullable', 'string', 'max:50'],
            'billing_cycle' => ['nullable', 'string', 'in:monthly,quarterly,annual,lifetime'],
            'status' => ['nullable', 'string', 'in:trial,active,past_due,suspended,cancelled'],
            'monthly_price' => ['nullable', 'numeric', 'min:0'],
            'billing_date' => ['nullable', 'date'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'seller_name' => ['nullable', 'string', 'max:100'],
            'max_users' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'max_branches' => ['nullable', 'integer', 'min:1', 'max:500'],
            'can_create_branches' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Nombres de atributos personalizados.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tenant_id' => 'identificador o subdominio',
            'company_name' => 'nombre del negocio',
            'admin_name' => 'nombre del administrador',
            'admin_email' => 'correo electrónico',
            'password' => 'contraseña',
            'vertical' => 'vertical de negocio',
        ];
    }
}
