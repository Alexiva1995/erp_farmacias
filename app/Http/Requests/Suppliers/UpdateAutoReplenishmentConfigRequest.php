<?php

declare(strict_types=1);

namespace App\Http\Requests\Suppliers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAutoReplenishmentConfigRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Preparar los datos para validación (convertir cadenas vacías en nulos).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('supplier_id')) {
            $this->merge([
                'supplier_id' => $this->supplier_id ? (int) $this->supplier_id : null,
            ]);
        }

        if ($this->has('group_ids')) {
            $this->merge([
                'group_ids' => is_array($this->group_ids) ? array_values(array_filter($this->group_ids)) : null,
            ]);
        }
    }

    /**
     * Obtener las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'                => ['sometimes', 'string', 'max:100'],
            'is_active'           => ['sometimes', 'boolean'],
            'tipo_filtracion'     => ['sometimes', 'in:average,sales,combinado'],
            'lapso_de_tiempo'     => ['sometimes', 'string', 'in:7 days,15 days,1 month,3 month,6 month,1 year'],
            'min_solicitar'       => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'con_descuento'       => ['sometimes', 'boolean'],
            'exclude_colombian'   => ['sometimes', 'boolean'],
            'exclude_novaventa'   => ['sometimes', 'boolean'],
            'stock_filter'        => ['sometimes', 'nullable', 'string', 'in:fallas,all'],
            'supplier_id'         => ['sometimes', 'nullable', 'integer', 'exists:suppliers,id'],
            'group_ids'           => ['sometimes', 'nullable', 'array'],
            'group_ids.*'         => ['integer'],
            'schedule_expression' => ['sometimes', 'string', 'max:50'],
        ];
    }
}
