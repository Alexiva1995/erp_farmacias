<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseBookEntry extends Model
{
    protected $table = 'purchase_book_entries';

    protected $fillable = [
        'purchase_book_id',
        'invoice_id',
        'retention_id',
        'operation_number',
        'operation_date',
        'rif',
        'supplier_name',
        'import_planilla_number',
        'import_dossier_number',
        'invoice_number',
        'control_number',
        'debit_note_number',
        'credit_note_number',
        'affected_invoice_number',
        'document_type',
        'transaction_type',
        'total_purchases_with_vat',
        'exempt_purchases',
        'general_taxable_base',
        'general_aliquot',
        'general_vat',
        'reduced_taxable_base',
        'reduced_aliquot',
        'reduced_vat',
        'vat_withheld',
        'withholding_receipt_number',
        'is_manual_override',
    ];

    protected $casts = [
        'operation_number' => 'integer',
        'operation_date' => 'date',
        'total_purchases_with_vat' => 'decimal:4',
        'exempt_purchases' => 'decimal:4',
        'general_taxable_base' => 'decimal:4',
        'general_aliquot' => 'decimal:4',
        'general_vat' => 'decimal:4',
        'reduced_taxable_base' => 'decimal:4',
        'reduced_aliquot' => 'decimal:4',
        'reduced_vat' => 'decimal:4',
        'vat_withheld' => 'decimal:4',
        'is_manual_override' => 'boolean',
    ];

    public function purchaseBook(): BelongsTo
    {
        return $this->belongsTo(PurchaseBook::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function retention(): BelongsTo
    {
        return $this->belongsTo(Retention::class);
    }

    /**
     * Formatear RIF para asegurar formato estándar (ej: J-08518977-7).
     */
    public static function formatRif(?string $rif): ?string
    {
        if (empty($rif) || strtoupper(trim((string) $rif)) === 'N/A') {
            return $rif;
        }

        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $rif));

        if (preg_match('/^([A-Z])(\d+)(\d)$/', $clean, $matches)) {
            return "{$matches[1]}-{$matches[2]}-{$matches[3]}";
        }

        return $rif;
    }

    public function getRifAttribute($value): ?string
    {
        return static::formatRif($value);
    }

    public function setRifAttribute($value): void
    {
        $this->attributes['rif'] = static::formatRif($value);
    }

    /**
     * Priorizar la razón social del proveedor sobre el nombre comercial.
     */
    public function getSupplierNameAttribute($value): string
    {
        $socialReason = $this->invoice?->supplier?->social_reason
            ?? $this->retention?->supplier?->social_reason;

        return !empty($socialReason) ? $socialReason : ($value ?: 'PROVEEDOR VARIOS');
    }

    /**
     * Si la entrada es una retención y no tiene control_number explícito,
     * obtener dinámicamente el número de control de la(s) factura(s) afectada(s).
     */
    public function getControlNumberAttribute($value): ?string
    {
        if (!empty($value)) {
            return $value;
        }

        if ($this->retention_id) {
            $invoices = $this->retention?->invoices;
            if ($invoices && $invoices->isNotEmpty()) {
                $ctrl = $invoices->pluck('control_number')->filter()->implode(', ');
                if (!empty($ctrl)) {
                    return $ctrl;
                }
            }
        }

        if ($this->invoice_id && $this->relationLoaded('invoice')) {
            return $this->invoice?->control_number;
        }

        return $value;
    }
}
