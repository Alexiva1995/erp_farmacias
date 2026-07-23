<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreLaboratoryGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'laboratory_ids' => 'nullable|array',
            'laboratory_ids.*' => 'exists:laboratories,id'
        ];
    }
}
