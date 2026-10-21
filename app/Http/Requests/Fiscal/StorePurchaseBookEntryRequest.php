<?php

declare(strict_types=1);

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseBookEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operation_date' => ['required', 'date'],
            'rif' => ['required', 'string', 'max:20'],
            'supplier_name' => ['required', 'string', 'max:255'],
            'import_planilla_number' => ['nullable', 'string', 'max:50'],
            'import_dossier_number' => ['nullable', 'string', 'max:50'],
            'invoice_number' => ['nullable', 'string', 'max:50'],
            'control_number' => ['nullable', 'string', 'max:50'],
            'debit_note_number' => ['nullable', 'string', 'max:50'],
            'credit_note_number' => ['nullable', 'string', 'max:50'],
            'affected_invoice_number' => ['nullable', 'string', 'max:50'],
            'document_type' => ['required', 'string', 'max:100'],
            'transaction_type' => ['required', 'string', 'max:20'],
            'total_purchases_with_vat' => ['required', 'numeric', 'min:0'],
            'exempt_purchases' => ['nullable', 'numeric', 'min:0'],
            'general_taxable_base' => ['nullable', 'numeric', 'min:0'],
            'general_aliquot' => ['nullable', 'numeric', 'min:0'],
            'general_vat' => ['nullable', 'numeric', 'min:0'],
            'reduced_taxable_base' => ['nullable', 'numeric', 'min:0'],
            'reduced_aliquot' => ['nullable', 'numeric', 'min:0'],
            'reduced_vat' => ['nullable', 'numeric', 'min:0'],
            'vat_withheld' => ['nullable', 'numeric', 'min:0'],
            'withholding_receipt_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}
