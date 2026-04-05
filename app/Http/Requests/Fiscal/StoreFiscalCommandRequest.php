<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class StoreFiscalCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'command' => 'required|string|in:REPORT_Z,REPORT_X,ANNUL_INVOICE,REPRINT_INVOICE,DEBIT_NOTE',
            'payload' => 'nullable|array',
            'payload.invoice_number' => 'required_if:command,ANNUL_INVOICE,REPRINT_INVOICE,DEBIT_NOTE|string',
        ];
    }
}
