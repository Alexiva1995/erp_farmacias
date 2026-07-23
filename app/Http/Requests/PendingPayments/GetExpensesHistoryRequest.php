<?php

namespace App\Http\Requests\PendingPayments;

use Illuminate\Foundation\Http\FormRequest;

class GetExpensesHistoryRequest extends FormRequest
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
            'page' => 'integer|min:1',
            'itemsPerPage' => 'integer|min:1|max:100',
        ];
    }
}
