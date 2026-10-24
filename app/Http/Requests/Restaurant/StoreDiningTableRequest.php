<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiningTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dining_zone_id' => 'required|exists:dining_zones,id',
            'name' => 'required|string|max:50',
            'capacity' => 'required|integer|min:1|max:50',
            'shape' => 'nullable|string|in:square,round,rectangle,bar',
            'pos_x' => 'nullable|numeric',
            'pos_y' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'rotation' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
        ];
    }
}
