<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesBookEntry extends Model
{
    protected $table = 'sales_book_entries';

    protected $fillable = [
        'sales_book_id',
        'fiscal_z_report_id',
        'fiscal_command_id',
        'operation_number',
        'operation_date',
        'identification',
        'client_name',
        'export_planilla_number',
        'document_type',
        'invoice_number',
        'control_number',
        'z_report_number',
        'z_first_invoice',
        'z_last_invoice',
        'machine_serial',
        'debit_note_number',
        'credit_note_number',
        'affected_invoice_number',
        'transaction_type',
        'total_sales_with_vat',
        'non_taxable_sales',
        'taxpayer_taxable_base',
        'taxpayer_aliquot',
        'taxpayer_vat',
        'non_taxpayer_taxable_base',
        'non_taxpayer_aliquot',
        'non_taxpayer_vat',
        'vat_withheld',
        'withholding_receipt_number',
        'withholding_receipt_date',
        'igtf_amount',
        'is_manual_override',
    ];

    protected $casts = [
        'operation_number' => 'integer',
        'operation_date' => 'date',
        'withholding_receipt_date' => 'date',
        'total_sales_with_vat' => 'decimal:4',
        'non_taxable_sales' => 'decimal:4',
        'taxpayer_taxable_base' => 'decimal:4',
        'taxpayer_aliquot' => 'decimal:4',
        'taxpayer_vat' => 'decimal:4',
        'non_taxpayer_taxable_base' => 'decimal:4',
        'non_taxpayer_aliquot' => 'decimal:4',
        'non_taxpayer_vat' => 'decimal:4',
        'vat_withheld' => 'decimal:4',
        'igtf_amount' => 'decimal:4',
        'is_manual_override' => 'boolean',
    ];

    public function salesBook(): BelongsTo
    {
        return $this->belongsTo(SalesBook::class);
    }

    public function fiscalZReport(): BelongsTo
    {
        return $this->belongsTo(FiscalZReport::class);
    }

    public function fiscalCommand(): BelongsTo
    {
        return $this->belongsTo(FiscalCommand::class);
    }
}
