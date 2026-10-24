<?php

namespace App\Http\Requests\Restaurant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiningLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tables' => 'required|array',
            'tables.*.id' => 'required|exists:dining_tables,id',
            'tables.*.pos_x' => 'required|numeric',
            'tables.*.pos_y' => 'required|numeric',
            'tables.*.width' => 'nullable|numeric',
            'tables.*.height' => 'nullable|numeric',
            'tables.*.rotation' => 'nullable|numeric',
        ];
    }
}
