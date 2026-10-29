<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBookEntry extends Model
{
    protected $table = 'inventory_book_entries';

    protected $fillable = [
        'inventory_book_id',
        'product_id',
        'operation_number',
        'product_code',
        'product_description',
        'unit_cost_bs',
        'initial_stock',
        'purchase_entries',
        'sales_exits',
        'withdrawals',
        'self_consumptions',
        'final_stock',
        'previous_value_bs',
        'purchase_entries_cost_bs',
        'sales_exits_cost_bs',
        'withdrawals_cost_bs',
        'self_consumptions_cost_bs',
        'final_inventory_value_bs',
        'iva_rate',
        'withdrawal_vat_bs',
        'withdrawal_reason',
        'is_manual_override',
    ];

    protected $casts = [
        'operation_number' => 'integer',
        'unit_cost_bs' => 'decimal:4',
        'initial_stock' => 'decimal:2',
        'purchase_entries' => 'decimal:2',
        'sales_exits' => 'decimal:2',
        'withdrawals' => 'decimal:2',
        'self_consumptions' => 'decimal:2',
        'final_stock' => 'decimal:2',
        'previous_value_bs' => 'decimal:4',
        'purchase_entries_cost_bs' => 'decimal:4',
        'sales_exits_cost_bs' => 'decimal:4',
        'withdrawals_cost_bs' => 'decimal:4',
        'self_consumptions_cost_bs' => 'decimal:4',
        'final_inventory_value_bs' => 'decimal:4',
        'iva_rate' => 'decimal:4',
        'withdrawal_vat_bs' => 'decimal:4',
        'is_manual_override' => 'boolean',
    ];

    public function inventoryBook(): BelongsTo
    {
        return $this->belongsTo(InventoryBook::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes()->withTrashed();
    }
}
