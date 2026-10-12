<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleMenuPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'menu_permissions' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'menu_permissions.array' => 'Los permisos de menú deben ser una lista válida.',
        ];
    }
}
