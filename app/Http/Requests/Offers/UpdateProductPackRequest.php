<?php

namespace App\Http\Requests\Offers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductPackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:100',
            'pack_config' => 'sometimes|required|array',
            'total_price' => 'sometimes|required|numeric|min:0',
            'max_quantity' => 'nullable|integer|min:1',
            'max_sale_date' => 'nullable|date|after_or_equal:today',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'integer|min:1|max:7',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'sales_channel' => 'nullable|string|in:all,dine_in,takeaway_delivery',
            'is_active' => 'boolean',
        ];
    }
}
