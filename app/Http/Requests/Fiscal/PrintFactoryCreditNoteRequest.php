<?php

declare(strict_types=1);

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class PrintFactoryCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_number' => 'required|string|max:50',
            'machine_serial' => 'nullable|string|max:50',
            'invoice_date'   => 'required|date',
            'refund_amount'  => 'required|numeric|min:0.01',
            'client_name'    => 'required|string|max:255',
            'client_rif'     => 'required|string|max:50',
            'is_taxable'     => 'required|boolean',
            'description'    => 'nullable|string|max:255',
            'ip'             => 'nullable|string|max:100',
            'port'           => 'nullable|integer|min:1|max:65535',
        ];
    }
}
