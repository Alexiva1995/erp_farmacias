<?php

declare(strict_types=1);

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class IndexInventoryBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'status' => ['nullable', 'string', 'in:open,closed'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
