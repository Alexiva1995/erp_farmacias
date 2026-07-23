<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreLaboratoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'group_id' => 'nullable|exists:groups_laboratories,id',
            'parent_id' => 'nullable|exists:laboratories,id'
        ];
    }
}
