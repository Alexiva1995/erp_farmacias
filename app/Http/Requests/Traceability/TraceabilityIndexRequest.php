<?php

namespace App\Http\Requests\Traceability;

use Illuminate\Foundation\Http\FormRequest;

class TraceabilityIndexRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'itemsPerPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sortBy' => ['nullable', 'string', 'max:50'],
            'orderBy' => ['nullable', 'string', 'in:asc,desc,ASC,DESC'],
            'startDate' => ['nullable', 'date_format:Y-m-d'],
            'endDate' => ['nullable', 'date_format:Y-m-d'],
            'movement_type' => ['nullable'],
            'movement_type.*' => ['string', 'in:sale,purchase,return,adjustment,loss,expired,verification'],
            'exclude_movement_types' => ['nullable'],
            'exclude_movement_types.*' => ['string', 'in:sale,purchase,return,adjustment,loss,expired,verification'],
            'is_psychotropic' => ['nullable', 'boolean'],
            'laboratoryId' => ['nullable', 'integer', 'exists:laboratories,id'],
            'hasStock' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('hasStock')) {
            $this->merge([
                'hasStock' => filter_var($this->input('hasStock'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }

        if ($this->has('movement_type')) {
            $val = $this->input('movement_type');
            if (is_string($val)) {
                $val = array_filter(explode(',', $val));
            }
            if (is_array($val)) {
                $this->merge(['movement_type' => array_values($val)]);
            }
        }

        if ($this->has('exclude_movement_types')) {
            $val = $this->input('exclude_movement_types');
            if (is_string($val)) {
                $val = array_filter(explode(',', $val));
            }
            if (is_array($val)) {
                $this->merge(['exclude_movement_types' => array_values($val)]);
            }
        }
    }
}
