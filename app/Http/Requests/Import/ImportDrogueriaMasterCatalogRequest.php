<?php

declare(strict_types=1);

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;

class ImportDrogueriaMasterCatalogRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para importar catálogo del master.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'master_supplier_id' => 'required|integer',
            'margin_percentage' => 'nullable|numeric|min:0|max:500',
            'default_credit_days' => 'nullable|integer|min:0|max:180',
            'default_stock_if_zero' => 'nullable|numeric|min:0',
            'only_with_stock' => 'nullable|boolean',
        ];
    }

    /**
     * Mensajes de error en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'master_supplier_id.required' => 'Debe seleccionar un proveedor o droguería del master.',
            'master_supplier_id.integer' => 'El identificador del proveedor debe ser un número entero.',
            'margin_percentage.numeric' => 'El margen debe ser un valor numérico.',
            'default_credit_days.integer' => 'Los días de crédito deben ser un número entero.',
            'default_stock_if_zero.numeric' => 'El stock predeterminado debe ser un número válido.',
        ];
    }
}
