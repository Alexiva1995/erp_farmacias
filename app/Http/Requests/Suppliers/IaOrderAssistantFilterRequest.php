<?php

namespace App\Http\Requests\Suppliers;

use Illuminate\Foundation\Http\FormRequest;

class IaOrderAssistantFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => 'nullable|integer|min:1',
            'itemsPerPage' => 'nullable|integer|min:-1|max:1000000',
            'tipo_filtracion' => 'nullable|string|in:average,sales,combinado',
            'tipo_vista' => 'nullable|boolean',
            'lapso_de_tiempo' => 'nullable|string',
            'stock' => 'nullable|string|in:exceso,fallas,all',
            'hasStock' => 'nullable|string',
            'isColombian' => 'nullable|boolean',
            'isNovaventa' => 'nullable|boolean',
            'tipo_exclusion' => 'nullable',
            'con_descuento' => 'nullable|boolean',
            'with_suppliers' => 'nullable|boolean',
            'show_ignored' => 'nullable|boolean',
            'with_trend' => 'nullable|boolean',
            'q' => 'nullable|string|max:255',
            'sortBy' => 'nullable|string',
            'orderBy' => 'nullable|string|in:asc,desc',
            'laboratoryId' => 'nullable|array',
            'groups' => 'nullable|array',
            'supplier_id' => 'nullable|integer',
        ];
    }
}
