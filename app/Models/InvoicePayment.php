<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    protected $fillable = [
        'payment_date',
        'amount',
        'payment_method',
        'reference',
        'status',
        'payment_by',
        'photo_url',
        'method'
    ];

    protected $casts = [
        'payment_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_by');
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'invoice_payment_invoice', 'payment_id', 'invoice_id');
    }
}
