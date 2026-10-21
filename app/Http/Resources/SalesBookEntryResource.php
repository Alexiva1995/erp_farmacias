<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesBookEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sales_book_id' => $this->sales_book_id,
            'fiscal_z_report_id' => $this->fiscal_z_report_id,
            'fiscal_command_id' => $this->fiscal_command_id,
            'operation_number' => $this->operation_number,
            'operation_date' => $this->operation_date ? $this->operation_date->format('Y-m-d') : null,
            'identification' => $this->identification,
            'client_name' => $this->client_name,
            'export_planilla_number' => $this->export_planilla_number,
            'document_type' => $this->document_type,
            'invoice_number' => $this->invoice_number,
            'control_number' => $this->control_number,
            'z_report_number' => $this->z_report_number,
            'z_first_invoice' => $this->z_first_invoice,
            'z_last_invoice' => $this->z_last_invoice,
            'machine_serial' => $this->machine_serial,
            'debit_note_number' => $this->debit_note_number,
            'credit_note_number' => $this->credit_note_number,
            'affected_invoice_number' => $this->affected_invoice_number,
            'transaction_type' => $this->transaction_type,
            'total_sales_with_vat' => (float) $this->total_sales_with_vat,
            'non_taxable_sales' => (float) $this->non_taxable_sales,
            'taxpayer_taxable_base' => (float) $this->taxpayer_taxable_base,
            'taxpayer_aliquot' => (float) $this->taxpayer_aliquot,
            'taxpayer_vat' => (float) $this->taxpayer_vat,
            'non_taxpayer_taxable_base' => (float) $this->non_taxpayer_taxable_base,
            'non_taxpayer_aliquot' => (float) $this->non_taxpayer_aliquot,
            'non_taxpayer_vat' => (float) $this->non_taxpayer_vat,
            'vat_withheld' => (float) $this->vat_withheld,
            'withholding_receipt_number' => $this->withholding_receipt_number,
            'withholding_receipt_date' => $this->withholding_receipt_date ? $this->withholding_receipt_date->format('Y-m-d') : null,
            'igtf_amount' => (float) $this->igtf_amount,
            'is_manual_override' => (bool) $this->is_manual_override,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
