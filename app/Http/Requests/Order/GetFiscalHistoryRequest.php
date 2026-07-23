<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class GetFiscalHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'page' => 'nullable|integer',
            'itemsPerPage' => 'nullable|integer',
            'sortBy' => 'nullable|string',
            'orderBy' => 'nullable|string'
        ];
    }
}
