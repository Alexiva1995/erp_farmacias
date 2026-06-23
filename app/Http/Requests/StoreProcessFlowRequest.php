<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcessFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'phases' => ['required', 'array', 'min:1'],
            'phases.*.name' => ['required', 'string', 'max:255'],
            'phases.*.description' => ['nullable', 'string', 'max:500'],
            'phases.*.sort_order' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del flujo es obligatorio.',
            'phases.required' => 'Debe ingresar al menos una fase.',
            'phases.*.name.required' => 'El nombre de cada fase es obligatorio.',
        ];
    }
}
