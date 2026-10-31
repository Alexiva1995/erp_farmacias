<?php

declare(strict_types=1);

namespace App\Http\Requests\Wholesale;

use Illuminate\Foundation\Http\FormRequest;

class CompletePackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'packages_count' => 'required|integer|min:1',
            'cold_boxes_count' => 'nullable|integer|min:0',
            'items' => 'required|array|min:1',
            'items.*.order_detail_id' => 'required|integer|exists:order_details,id',
            'items.*.checked_quantity' => 'required|numeric|min:0',
        ];
    }
}
