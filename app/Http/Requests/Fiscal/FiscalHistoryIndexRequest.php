<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class FiscalHistoryIndexRequest extends FormRequest
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
            'q'            => ['nullable', 'string', 'max:100'],
            'startDate'    => ['nullable', 'date'],
            'endDate'      => ['nullable', 'date', 'after_or_equal:startDate'],
            'page'         => ['nullable', 'integer', 'min:1'],
            'itemsPerPage' => ['nullable', 'integer', 'min:-1', 'max:100'],
            'sortBy'       => ['nullable', 'string', 'in:id,fiscal_id,invoice_number,identification,business_name,invoice_date,exempt_amount,iva_amount,total_amount'],
            'orderBy'      => ['nullable', 'string', 'in:asc,desc,ASC,DESC'],
        ];
    }
}
