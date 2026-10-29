<?php

declare(strict_types=1);

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVatRetentionBookEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_number' => ['nullable', 'string', 'max:100'],
            'control_number' => ['nullable', 'string', 'max:100'],
            'document_type' => ['nullable', 'string', 'in:01,02,03'],
            'affected_document_number' => ['nullable', 'string', 'max:100'],
            'total_document_amount' => ['nullable', 'numeric', 'min:0'],
            'taxable_base' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'withheld_amount' => ['nullable', 'numeric', 'min:0'],
            'exempt_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
