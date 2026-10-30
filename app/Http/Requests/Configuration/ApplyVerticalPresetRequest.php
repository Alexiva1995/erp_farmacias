<?php

declare(strict_types=1);

namespace App\Http\Requests\Configuration;

use App\Enums\BusinessVerticalEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyVerticalPresetRequest extends FormRequest
{
    /**
     * Determinar si el usuario está autorizado para realizar la solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para la aplicación del preset de vertical.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $customKeys = array_keys(app(\App\Contracts\VerticalPresetServiceInterface::class)->getCustomPresets());
        $allowedVerticals = array_values(array_unique(array_merge(BusinessVerticalEnum::values(), $customKeys)));

        return [
            'vertical' => [
                'required',
                'string',
                Rule::in($allowedVerticals),
            ],
            'preserve_identity' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vertical.required' => 'La vertical de negocio es obligatoria.',
            'vertical.string' => 'La vertical de negocio debe ser una cadena de texto.',
            'vertical.in' => 'La vertical seleccionada no es válida. Opciones permitidas: ' . implode(', ', BusinessVerticalEnum::values()),
            'preserve_identity.boolean' => 'El campo conservar identidad debe ser un valor booleano.',
        ];
    }
}
