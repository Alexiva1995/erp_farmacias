<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryStockFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string'],
            'hasStock' => ['nullable', 'boolean'],
            'laboratoryId' => ['nullable', 'integer', 'exists:laboratories,id'],
            'viewType' => ['nullable', 'string', 'in:individual,group'],
            'stock' => ['nullable', 'string', 'in:exceso,fallas,all'],
            'expProd' => ['nullable', 'boolean'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
            'orderBy' => ['nullable', 'string'],
            'sortBy' => ['nullable', 'string'],
            'days' => ['nullable', 'integer', 'min:1'],
            'isStrictSearch' => ['nullable', 'boolean'],
            'tipo_filtracion' => ['nullable', 'string', 'in:average,sales,combinado'],
            'isColombian' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'itemsPerPage' => ['nullable', 'integer', 'min:1'],
            'formato' => ['nullable', 'string', 'in:xlsx,csv,pdf'],
        ];
    }
}
