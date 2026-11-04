<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseBookExcludedInvoice extends Model
{
    protected $table = 'purchase_book_excluded_invoices';

    protected $fillable = [
        'purchase_book_id',
        'invoice_id',
        'invoice_number',
        'control_number',
        'retention_id',
    ];

    public function purchaseBook(): BelongsTo
    {
        return $this->belongsTo(PurchaseBook::class, 'purchase_book_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function retention(): BelongsTo
    {
        return $this->belongsTo(Retention::class, 'retention_id');
    }
}
