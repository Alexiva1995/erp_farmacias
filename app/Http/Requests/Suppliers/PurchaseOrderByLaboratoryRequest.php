<?php

declare(strict_types=1);

namespace App\Http\Requests\Suppliers;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderByLaboratoryRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para la consulta de órdenes por laboratorio.
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'integer', 'in:0,1,2'],
            'search' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'itemsPerPage' => ['nullable', 'integer', 'min:-1', 'max:100'],
            'sortBy' => ['nullable', 'string', 'in:laboratory_name,total_skus,total_units,total_amount_usd'],
            'orderBy' => ['nullable', 'string', 'in:asc,desc,ASC,DESC'],
        ];
    }
}
