<?php

namespace App\Http\Requests\Credits;

use Illuminate\Foundation\Http\FormRequest;

class IndexCreditPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'items_per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'client' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'currency' => ['nullable', 'string', 'in:USD,COP,BS'],
            'sort_by' => ['nullable', 'string'],
            'order_by' => ['nullable', 'string', 'in:asc,desc,ASC,DESC'],
        ];
    }
}
