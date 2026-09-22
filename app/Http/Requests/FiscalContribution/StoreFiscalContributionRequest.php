<?php

declare(strict_types=1);

namespace App\Http\Requests\FiscalContribution;

use Illuminate\Foundation\Http\FormRequest;

class StoreFiscalContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period'            => ['required', 'string', 'max:20'],
            'tax_type'          => ['required', 'string', 'max:50'],
            'document_number'   => ['required', 'string', 'max:50'],
            'operation_date'    => ['required', 'date'],
            'due_date'          => ['required', 'date'],
            'amount'            => ['required', 'numeric', 'min:0'],
            'status'            => ['nullable', 'string', 'in:pending,paid,expired'],
            'payment_date'      => ['nullable', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'source'            => ['nullable', 'string', 'in:manual,smart_paste,bot'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ];
    }
}
