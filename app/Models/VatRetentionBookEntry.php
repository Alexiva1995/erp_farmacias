<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VatRetentionBookEntry extends Model
{
    protected $table = 'vat_retention_book_entries';

    protected $fillable = [
        'vat_retention_book_id',
        'retention_id',
        'invoice_id',
        'operation_number',
        'agent_rif',
        'fiscal_period',
        'document_date',
        'operation_type',
        'document_type',
        'supplier_rif',
        'supplier_name',
        'document_number',
        'control_number',
        'total_document_amount',
        'taxable_base',
        'tax_amount',
        'withheld_amount',
        'affected_document_number',
        'retention_number',
        'exempt_amount',
        'tax_rate',
        'import_dossier_number',
    ];

    protected $casts = [
        'operation_number' => 'integer',
        'document_date' => 'date',
        'total_document_amount' => 'decimal:2',
        'taxable_base' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'withheld_amount' => 'decimal:2',
        'exempt_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(VatRetentionBook::class, 'vat_retention_book_id');
    }

    public function retention(): BelongsTo
    {
        return $this->belongsTo(Retention::class, 'retention_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    /**
     * Retorna la línea con formato exacto para el TXT del SENIAT (16 columnas separadas por tabulación).
     */
    public function toSeniatTxtLine(): string
    {
        $agentRif = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->agent_rif));
        $fiscalPeriod = (string) $this->fiscal_period;
        $docDate = \Carbon\Carbon::parse($this->document_date)->format('Y-m-d');
        $opType = mb_strtoupper(trim((string) ($this->operation_type ?: 'C')));
        $docType = str_pad((string) ($this->document_type ?: '01'), 2, '0', STR_PAD_LEFT);
        $supplierRif = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->supplier_rif));
        $docNumber = trim((string) $this->document_number);
        $controlNumber = trim((string) ($this->control_number ?: ''));
        $totalDoc = number_format((float) ($this->total_document_amount ?? 0), 2, '.', '');
        $taxableBase = number_format((float) ($this->taxable_base ?? 0), 2, '.', '');
        $withheld = number_format((float) ($this->withheld_amount ?? 0), 2, '.', '');
        $affectedDoc = trim((string) ($this->affected_document_number ?: '0'));
        $retentionNum = trim((string) $this->retention_number);
        $exempt = number_format((float) ($this->exempt_amount ?? 0), 2, '.', '');
        $taxRate = number_format((float) ($this->tax_rate ?? 16), 1, '.', '');
        $dossier = trim((string) ($this->import_dossier_number ?: '0'));

        $columns = [
            $agentRif,
            $fiscalPeriod,
            $docDate,
            $opType,
            $docType,
            $supplierRif,
            $docNumber,
            $controlNumber,
            $totalDoc,
            $taxableBase,
            $withheld,
            $affectedDoc,
            $retentionNum,
            $exempt,
            $taxRate,
            $dossier,
        ];

        return implode("\t", $columns);
    }
}
