<?php

declare(strict_types=1);

namespace App\Http\Requests\Returns;

use Illuminate\Foundation\Http\FormRequest;

class SearchReturnOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identification' => 'nullable|string|max:100',
            'order_id' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'itemsPerPage' => 'nullable|integer|min:-1|max:100',
            'sortBy' => 'nullable|string|max:50',
            'orderBy' => 'nullable|string|in:asc,desc',
        ];
    }
}
