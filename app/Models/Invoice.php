<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'supplier_id',
        'auto_order_id',
        'invoice_number',
        'control_number',
        'exp_date',
        'payment_date',
        'received_date',
        'currency',
        'discount_rule_id',
        'exempt_amount',
        'taxable_base',
        'tax_amount',
        'total_amount',
        'exchange_rate',
        'total_usd',
        'status',
        'uploaded_by',
        'registered_by',
        'ordered_by',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function autoOrder(): BelongsTo
    {
        return $this->belongsTo(AutoOrder::class);
    }

    public function discountRule(): BelongsTo
    {
        return $this->belongsTo(DiscountRule::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function details()
    {
        return $this->hasMany(InvoiceDetail::class);
    }

    public function payments()
    {
        return $this->belongsToMany(InvoicePayment::class, 'invoice_payment_invoice', 'invoice_id', 'payment_id');
    }

    public function psychotropicControls()
    {
        return $this->hasMany(PsychotropicControl::class);
    }
}
