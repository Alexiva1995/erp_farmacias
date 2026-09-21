<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FiscalZReport extends Model
{
    use HasFactory;

    protected $table = 'fiscal_z_reports';

    protected $fillable = [
        'report_number',
        'report_date',
        'opening_time',
        'closing_time',
        'first_invoice_number',
        'last_invoice_number',
        'invoices_count',
        'exempt_amount',
        'base_16_amount',
        'iva_amount',
        'igtf_base_amount',
        'igtf_amount',
        'total_amount',
        'status',
        'image_path',
        'ai_verification_notes',
    ];

    protected $casts = [
        'report_number'    => 'integer',
        'report_date'      => 'date:Y-m-d',
        'invoices_count'   => 'integer',
        'exempt_amount'    => 'decimal:2',
        'base_16_amount'   => 'decimal:2',
        'iva_amount'       => 'decimal:2',
        'igtf_base_amount' => 'decimal:2',
        'igtf_amount'      => 'decimal:2',
        'total_amount'     => 'decimal:2',
    ];
}
