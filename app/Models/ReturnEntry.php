<?php

namespace App\Models;

use App\Enums\ReturnFiscalStatusEnum;
use App\Enums\ReturnRefundMethodEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnEntry extends Model
{
    protected $table = 'returns';

    const REJECTED = 'Rejected';
    const APPROVED = 'Approved';

    protected $fillable = [
        'order_id',
        'generated_by_id',
        'approved_by_id',
        'product_id',
        'quantity',
        'amount_refunded',
        'refund_method',
        'restock_inventory',
        'reason',
        'return_date',
        'status',
        'fiscal_command_id',
        'credit_note_number',
        'fiscal_status',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'amount_refunded' => 'float',
        'restock_inventory' => 'boolean',
        'return_date' => 'datetime',
        'refund_method' => ReturnRefundMethodEnum::class,
        'fiscal_status' => ReturnFiscalStatusEnum::class,
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function fiscalCommand(): BelongsTo
    {
        return $this->belongsTo(FiscalCommand::class, 'fiscal_command_id');
    }
}
