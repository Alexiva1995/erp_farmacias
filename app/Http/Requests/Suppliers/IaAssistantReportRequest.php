<?php

namespace App\Http\Requests\Suppliers;

use Illuminate\Foundation\Http\FormRequest;

class IaAssistantReportRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     */
    public function rules(): array
    {
        return [
            'itemsPerPage' => 'nullable|integer|min:-1',
            'page' => 'nullable|integer|min:1',
            'tipo_filtracion' => 'nullable|string|in:average,sales,combinado',
            'tipo_vista' => 'nullable|boolean',
            'lapso_de_tiempo' => 'nullable|string',
            'with_suppliers' => 'nullable|boolean',
            'con_descuento' => 'nullable|boolean',
            'with_trend' => 'nullable|boolean',
            'orderBy' => 'nullable|string',
            'sortBy' => 'nullable|string',
            'q' => 'nullable|string',
            'stock' => 'nullable|string|in:exceso,fallas,all',
            'laboratoryId' => 'nullable|array',
            'laboratoryId.*' => 'integer|exists:laboratories,id',
            'groups' => 'nullable|array',
            'groups.*' => 'integer',
            'isColombian' => 'nullable|boolean',
            'is_colombia' => 'nullable|boolean',
            'isNovaventa' => 'nullable|boolean',
            'product' => 'nullable|array',
            'product.*' => 'integer|exists:products,id',
            'hasStock' => 'nullable|string',
            'formato' => 'nullable|string|in:xlsx,pdf',
        ];
    }
}
