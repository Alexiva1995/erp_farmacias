<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiningZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'background_style' => 'nullable|string|in:grid,wooden,concrete,grass',
            'width_meters' => 'nullable|numeric|min:2|max:100',
            'height_meters' => 'nullable|numeric|min:2|max:100',
            'layout_config' => 'nullable|array',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ];
    }
}
