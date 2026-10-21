<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseBookPeriodType;
use App\Enums\PurchaseBookStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseBook extends Model
{
    protected $table = 'purchase_books';

    protected $fillable = [
        'period_type',
        'year',
        'month',
        'fortnight',
        'start_date',
        'end_date',
        'status',
        'enable_zoom_snapshot',
        'total_purchases_with_vat',
        'total_exempt_purchases',
        'total_general_taxable_base',
        'total_general_vat',
        'total_reduced_taxable_base',
        'total_reduced_vat',
        'total_vat_withheld',
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
        'enable_zoom_snapshot' => 'boolean',
        'total_purchases_with_vat' => 'decimal:4',
        'total_exempt_purchases' => 'decimal:4',
        'total_general_taxable_base' => 'decimal:4',
        'total_general_vat' => 'decimal:4',
        'total_reduced_taxable_base' => 'decimal:4',
        'total_reduced_vat' => 'decimal:4',
        'total_vat_withheld' => 'decimal:4',
        'closed_at' => 'datetime',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(PurchaseBookEntry::class)->orderBy('operation_number', 'asc');
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
