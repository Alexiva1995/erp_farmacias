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
}
