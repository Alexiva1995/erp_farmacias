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
        'is_indexed', // ISSUE #3: Campo para facturas indexadas
        'discount_rule_id',
        'exempt_amount',
        'taxable_base',
        'tax_amount',
        'total_amount',
        'total_amount_discount',
        'exchange_rate',
        'total_usd',
        'status',
        'status_payment',
        'uploaded_by',
        'registered_by',
        'ordered_by',
        'created_invoice_date'
    ];

    /**
     * ISSUE #3: Casts para manejo correcto de tipos de datos
     */
    protected $casts = [
        'is_indexed' => 'boolean',
        'exp_date' => 'date',
        'payment_date' => 'date',
        'received_date' => 'date',
        'created_invoice_date' => 'date',
        'total_amount' => 'decimal:2',
        'total_amount_discount' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'total_usd' => 'decimal:2',
        'exempt_amount' => 'decimal:2',
        'taxable_base' => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];

    /**
     * ISSUE #3: Valores por defecto para nuevos registros
     */
    protected $attributes = [
        'is_indexed' => false, // Por defecto las facturas NO están indexadas
        'status' => 'loaded',
        'status_payment' => 0,
        'exempt_amount' => 0.00,
        'taxable_base' => 0.00,
        'tax_amount' => 0.00,
    ];

    public const FILLABLEHEADER = [
        'invoice_number',
        'control_number',
        'exp_date',
        'tax_amount',
        'total_amount',
    ];

    protected $fillableFromHeader = self::FILLABLEHEADER;

    protected $appends = ['outstanding_debt'];

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
    public function returns()
    {
        return $this->hasMany(InvoiceReturn::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'ordered';
    }

    public function canBeApproved(): bool
    {
        return $this->status === 'to_order';
    }
    public function getTotalReturns(): float
    {
        return $this->returns()->sum('amount_refunded');
    }

    /**
     * Obtiene la cantidad total de productos devueltos
     */
    public function getTotalReturnedQuantity(): float
    {
        return $this->returns()->sum('quantity');
    }
    public function getOutstandingDebtAttribute(): float
    {
        // Suma de pagos registrados
        $paid = $this->payments->sum('amount');

        // Deuda pendiente (nunca menos de cero)
        return max(0, $this->total_amount - $paid);
    }
}
