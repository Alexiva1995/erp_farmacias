<?php

namespace App\Http\Requests\Bi;

use Illuminate\Foundation\Http\FormRequest;

class SkuReportRequest extends FormRequest
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
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'laboratory_id' => ['nullable', 'integer'],
            'group_id' => ['nullable', 'integer'],
            'semaphore' => ['nullable', 'string', 'in:verde,amarillo,rojo,negro'],
            'is_active' => ['nullable', 'in:0,1,true,false'],
            'sortBy' => ['nullable', 'string'],
            'orderBy' => ['nullable', 'string', 'in:asc,desc'],
            'itemsPerPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
