<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseBookEntryResource extends JsonResource
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
            'purchase_book_id' => $this->purchase_book_id,
            'invoice_id' => $this->invoice_id,
            'retention_id' => $this->retention_id,
            'operation_number' => $this->operation_number,
            'operation_date' => $this->operation_date ? $this->operation_date->format('Y-m-d') : null,
            'rif' => $this->rif,
            'supplier_name' => $this->supplier_name,
            'import_planilla_number' => $this->import_planilla_number,
            'import_dossier_number' => $this->import_dossier_number,
            'invoice_number' => $this->invoice_number,
            'control_number' => $this->control_number,
            'debit_note_number' => $this->debit_note_number,
            'credit_note_number' => $this->credit_note_number,
            'affected_invoice_number' => $this->affected_invoice_number,
            'document_type' => $this->document_type,
            'transaction_type' => $this->transaction_type,
            'total_purchases_with_vat' => (float) $this->total_purchases_with_vat,
            'exempt_purchases' => (float) $this->exempt_purchases,
            'general_taxable_base' => (float) $this->general_taxable_base,
            'general_aliquot' => (float) $this->general_aliquot,
            'general_vat' => (float) $this->general_vat,
            'reduced_taxable_base' => (float) $this->reduced_taxable_base,
            'reduced_aliquot' => (float) $this->reduced_aliquot,
            'reduced_vat' => (float) $this->reduced_vat,
            'vat_withheld' => (float) $this->vat_withheld,
            'withholding_receipt_number' => $this->withholding_receipt_number,
            'is_manual_override' => (bool) $this->is_manual_override,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
