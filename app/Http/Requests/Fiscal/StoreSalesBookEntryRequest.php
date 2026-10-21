<?php

declare(strict_types=1);

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesBookEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operation_date' => ['required', 'date'],
            'identification' => ['required', 'string', 'max:20'],
            'client_name' => ['required', 'string', 'max:255'],
            'export_planilla_number' => ['nullable', 'string', 'max:50'],
            'document_type' => ['required', 'string', 'max:100'],
            'invoice_number' => ['nullable', 'string', 'max:50'],
            'control_number' => ['nullable', 'string', 'max:50'],
            'z_report_number' => ['nullable', 'string', 'max:50'],
            'z_first_invoice' => ['nullable', 'string', 'max:50'],
            'z_last_invoice' => ['nullable', 'string', 'max:50'],
            'machine_serial' => ['nullable', 'string', 'max:50'],
            'debit_note_number' => ['nullable', 'string', 'max:50'],
            'credit_note_number' => ['nullable', 'string', 'max:50'],
            'affected_invoice_number' => ['nullable', 'string', 'max:50'],
            'transaction_type' => ['required', 'string', 'max:20'],
            'total_sales_with_vat' => ['required', 'numeric'],
            'non_taxable_sales' => ['nullable', 'numeric'],
            'taxpayer_taxable_base' => ['nullable', 'numeric'],
            'taxpayer_aliquot' => ['nullable', 'numeric'],
            'taxpayer_vat' => ['nullable', 'numeric'],
            'non_taxpayer_taxable_base' => ['nullable', 'numeric'],
            'non_taxpayer_aliquot' => ['nullable', 'numeric'],
            'non_taxpayer_vat' => ['nullable', 'numeric'],
            'vat_withheld' => ['nullable', 'numeric'],
            'withholding_receipt_number' => ['nullable', 'string', 'max:50'],
            'withholding_receipt_date' => ['nullable', 'date'],
            'igtf_amount' => ['nullable', 'numeric'],
        ];
    }
}
