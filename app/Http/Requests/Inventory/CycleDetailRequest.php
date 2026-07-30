<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class CycleDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cycleId' => ['required', 'integer', 'exists:inventory_cycles,id'],
            'page' => ['nullable', 'integer', 'min:1'],
            'itemsPerPage' => ['nullable', 'integer', 'min:-1', 'max:500'],
            'q' => ['nullable', 'string', 'max:100'],
            'laboratoryId' => ['nullable', 'integer'],
            'discrepancyFilter' => ['nullable', 'string', 'in:with_discrepancy,surplus,shortage,exact'],
            'userId' => ['nullable', 'integer'],
            'supervisorId' => ['nullable', 'integer'],
            'sortBy' => ['nullable', 'string'],
            'orderBy' => ['nullable', 'string', 'in:asc,desc,ASC,DESC'],
        ];
    }
}
