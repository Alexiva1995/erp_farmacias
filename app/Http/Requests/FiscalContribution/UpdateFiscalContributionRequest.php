<?php

declare(strict_types=1);

namespace App\Http\Requests\FiscalContribution;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFiscalContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period'            => ['sometimes', 'required', 'string', 'max:20'],
            'tax_type'          => ['sometimes', 'required', 'string', 'max:50'],
            'document_number'   => ['sometimes', 'required', 'string', 'max:50'],
            'operation_date'    => ['sometimes', 'required', 'date'],
            'due_date'          => ['sometimes', 'required', 'date'],
            'amount'            => ['sometimes', 'required', 'numeric', 'min:0'],
            'status'            => ['nullable', 'string', 'in:pending,paid,expired'],
            'payment_date'      => ['nullable', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'source'            => ['nullable', 'string', 'in:manual,smart_paste,bot'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ];
    }
}
