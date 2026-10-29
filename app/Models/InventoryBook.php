<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseBookPeriodType;
use App\Enums\PurchaseBookStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBook extends Model
{
    protected $table = 'inventory_books';

    protected $fillable = [
        'period_type',
        'year',
        'month',
        'fortnight',
        'start_date',
        'end_date',
        'status',
        'include_negative_adjustments_as_withdrawals',
        'total_initial_units',
        'total_purchase_units',
        'total_sales_units',
        'total_withdrawal_units',
        'total_self_consumption_units',
        'total_final_units',
        'total_previous_value_bs',
        'total_purchase_cost_bs',
        'total_sales_cost_bs',
        'total_withdrawal_cost_bs',
        'total_self_consumption_cost_bs',
        'total_final_inventory_value_bs',
        'total_withdrawal_exempt_bs',
        'total_withdrawal_taxable_bs',
        'total_withdrawal_vat_bs',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'period_type' => PurchaseBookPeriodType::class,
        'status' => PurchaseBookStatus::class,
        'year' => 'integer',
        'month' => 'integer',
        'fortnight' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'include_negative_adjustments_as_withdrawals' => 'boolean',
        'total_initial_units' => 'decimal:2',
        'total_purchase_units' => 'decimal:2',
        'total_sales_units' => 'decimal:2',
        'total_withdrawal_units' => 'decimal:2',
        'total_self_consumption_units' => 'decimal:2',
        'total_final_units' => 'decimal:2',
        'total_previous_value_bs' => 'decimal:4',
        'total_purchase_cost_bs' => 'decimal:4',
        'total_sales_cost_bs' => 'decimal:4',
        'total_withdrawal_cost_bs' => 'decimal:4',
        'total_self_consumption_cost_bs' => 'decimal:4',
        'total_final_inventory_value_bs' => 'decimal:4',
        'total_withdrawal_exempt_bs' => 'decimal:4',
        'total_withdrawal_taxable_bs' => 'decimal:4',
        'total_withdrawal_vat_bs' => 'decimal:4',
        'closed_at' => 'datetime',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(InventoryBookEntry::class)->orderBy('operation_number', 'asc');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === PurchaseBookStatus::OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === PurchaseBookStatus::CLOSED;
    }
}
