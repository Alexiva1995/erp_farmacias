<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetPurchaseOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'selectedSupplier' => ['nullable', 'integer', 'exists:suppliers,id'],
            'search'           => ['nullable', 'string', 'max:255'],
            'start_date'       => ['nullable', 'date'],
            'end_date'         => ['nullable', 'date', 'after_or_equal:start_date'],
            'status'           => ['nullable', 'integer', 'in:0,1,2'],
            'page'             => ['nullable', 'integer', 'min:1'],
            'itemsPerPage'     => ['nullable', 'integer', 'min:-1', 'max:100000'],
        ];
    }
}
