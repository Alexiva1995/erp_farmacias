<?php

declare(strict_types=1);

namespace App\Http\Requests\Finances;

use Illuminate\Foundation\Http\FormRequest;

class GetTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date'   => ['nullable', 'date_format:Y-m-d'],
            'currency'   => ['nullable', 'string', 'in:USD,BS,COP'],
            'detailed'   => ['nullable', 'boolean'],
            'option'     => ['nullable', 'string'],
            'per_page'   => ['nullable', 'integer', 'min:1', 'max:100'],
            'page'       => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('detailed')) {
            $this->merge([
                'detailed' => filter_var($this->detailed, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
