<?php

declare(strict_types=1);

namespace App\Http\Requests\Suppliers;

use Illuminate\Foundation\Http\FormRequest;

class StoreAutoReplenishmentConfigRequest extends FormRequest
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
        $this->merge([
            'supplier_id' => $this->supplier_id ? (int) $this->supplier_id : null,
            'group_ids' => is_array($this->group_ids) ? array_values(array_filter($this->group_ids)) : null,
        ]);
    }

    /**
     * Obtener las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:100'],
            'is_active'           => ['boolean'],
            'tipo_filtracion'     => ['required', 'in:average,sales,combinado'],
            'lapso_de_tiempo'     => ['required', 'string', 'in:7 days,15 days,1 month,3 month,6 month,1 year'],
            'min_solicitar'       => ['nullable', 'numeric', 'min:0'],
            'con_descuento'       => ['boolean'],
            'exclude_colombian'   => ['boolean'],
            'exclude_novaventa'   => ['boolean'],
            'include_ignored'     => ['boolean'],
            'stock_filter'        => ['nullable', 'string', 'in:fallas,all'],
            'supplier_id'         => ['nullable', 'integer', 'exists:suppliers,id'],
            'group_ids'           => ['nullable', 'array'],
            'group_ids.*'         => ['integer'],
            'schedule_expression' => ['required', 'string', 'max:50'],
        ];
    }
}
