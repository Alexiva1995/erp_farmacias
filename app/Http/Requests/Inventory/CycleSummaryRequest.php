<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class CycleSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'itemsPerPage' => ['nullable', 'integer', 'min:-1', 'max:500'],
            'sortBy' => ['nullable', 'string', 'in:cycle_id,start_date,end_date,total_products,total_surplus,total_shortage,net_total'],
            'orderBy' => ['nullable', 'string', 'in:asc,desc,ASC,DESC'],
            'startDate' => ['nullable', 'date_format:Y-m-d'],
            'endDate' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'cycleStatus' => ['nullable', 'string', 'in:active,closed,cancelled'],
        ];
    }
}
