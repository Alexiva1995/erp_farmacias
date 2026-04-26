<?php

namespace App\Http\Requests\Bi;

use Illuminate\Foundation\Http\FormRequest;

class ExpiryReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'laboratory_id' => 'nullable|integer|exists:laboratories,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'group_id' => 'nullable|integer|exists:groups_products,id',
            'location_id' => 'nullable|integer',
        ];
    }
}
