<?php

declare(strict_types=1);

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFiscalZReportRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_invoice_number' => ['required', 'string', 'max:50'],
            'last_invoice_number'  => ['required', 'string', 'max:50'],
            'recalculate'          => ['nullable', 'boolean'],
            'exempt_amount'        => ['nullable', 'numeric'],
            'base_16_amount'       => ['nullable', 'numeric'],
            'iva_amount'           => ['nullable', 'numeric'],
            'igtf_amount'          => ['nullable', 'numeric'],
            'total_amount'         => ['nullable', 'numeric'],
        ];
    }
}
