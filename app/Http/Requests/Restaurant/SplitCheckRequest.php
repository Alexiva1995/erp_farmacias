<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class SplitCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string|in:seats_items,equal',
            // Para 'equal':
            'parts' => 'required_if:type,equal|nullable|integer|min:2|max:30',
            // Para 'seats_items':
            'splits' => 'required_if:type,seats_items|nullable|array|min:2',
            'splits.*.seat_number' => 'nullable|integer',
            'splits.*.detail_ids' => 'required_if:type,seats_items|array|min:1',
            'splits.*.detail_ids.*' => 'integer|exists:order_details,id',
        ];
    }
}
