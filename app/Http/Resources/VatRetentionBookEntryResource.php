<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VatRetentionBookEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vat_retention_book_id' => $this->vat_retention_book_id,
            'retention_id' => $this->retention_id,
            'invoice_id' => $this->invoice_id,
            'operation_number' => $this->operation_number,
            'agent_rif' => $this->agent_rif,
            'fiscal_period' => $this->fiscal_period,
            'document_date' => $this->document_date ? $this->document_date->format('Y-m-d') : null,
            'operation_type' => $this->operation_type,
            'document_type' => $this->document_type,
            'supplier_rif' => $this->supplier_rif,
            'supplier_name' => $this->supplier_name,
            'document_number' => $this->document_number,
            'control_number' => $this->control_number,
            'total_document_amount' => (float) $this->total_document_amount,
            'taxable_base' => (float) $this->taxable_base,
            'tax_amount' => (float) $this->tax_amount,
            'withheld_amount' => (float) $this->withheld_amount,
            'affected_document_number' => $this->affected_document_number,
            'retention_number' => $this->retention_number,
            'exempt_amount' => (float) $this->exempt_amount,
            'tax_rate' => (float) $this->tax_rate,
            'import_dossier_number' => $this->import_dossier_number,
        ];
    }
}
