<?php

namespace App\Http\Requests\Bi;

use Illuminate\Foundation\Http\FormRequest;

class LaboratoryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'group_by_corporate' => 'nullable|boolean',
            'metric' => 'nullable|string|in:total_units,total_revenue,total_stock,inventory_value',
            'page' => 'nullable|integer|min:1',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('group_by_corporate')) {
            $this->merge([
                'group_by_corporate' => filter_var($this->input('group_by_corporate'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
