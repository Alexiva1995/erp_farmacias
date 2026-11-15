<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use Illuminate\Foundation\Http\FormRequest;

class VerifyLicenseRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para verificación pública de llave de licencia.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'license_key' => ['required', 'string', 'max:100'],
            'domain' => ['nullable', 'string', 'max:150'],
            'tenant_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
