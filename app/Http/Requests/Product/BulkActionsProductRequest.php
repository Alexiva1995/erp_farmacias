<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class BulkActionsProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:products,id',
            'action' => 'required|string|in:delete,change-category,change-laboratory,toggle-active',
            'value' => 'nullable|integer'
        ];
    }
}
