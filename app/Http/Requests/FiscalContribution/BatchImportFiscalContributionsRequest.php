<?php

declare(strict_types=1);

namespace App\Http\Requests\FiscalContribution;

use Illuminate\Foundation\Http\FormRequest;

class BatchImportFiscalContributionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.period'            => ['required', 'string', 'max:20'],
            'items.*.tax_type'          => ['required', 'string', 'max:50'],
            'items.*.document_number'   => ['required', 'string', 'max:50'],
            'items.*.operation_date'    => ['required', 'date'],
            'items.*.due_date'          => ['required', 'date'],
            'items.*.amount'            => ['required', 'numeric', 'min:0'],
            'items.*.status'            => ['nullable', 'string', 'in:pending,paid,expired'],
            'items.*.notes'             => ['nullable', 'string'],
            'source'                    => ['nullable', 'string', 'in:smart_paste,bot,manual'],
        ];
    }
}
