<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseBookPeriodType;
use App\Enums\PurchaseBookStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesBook extends Model
{
    protected $table = 'sales_books';

    protected $fillable = [
        'period_type',
        'year',
        'month',
        'fortnight',
        'start_date',
        'end_date',
        'status',
        'enable_zoom_snapshot',
        'total_sales_with_vat',
        'total_non_taxable_sales',
        'total_taxpayer_taxable_base',
        'total_taxpayer_vat',
        'total_non_taxpayer_taxable_base',
        'total_non_taxpayer_vat',
        'total_vat_withheld',
        'total_igtf',
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
        'total_sales_with_vat' => 'decimal:4',
        'total_non_taxable_sales' => 'decimal:4',
        'total_taxpayer_taxable_base' => 'decimal:4',
        'total_taxpayer_vat' => 'decimal:4',
        'total_non_taxpayer_taxable_base' => 'decimal:4',
        'total_non_taxpayer_vat' => 'decimal:4',
        'total_vat_withheld' => 'decimal:4',
        'total_igtf' => 'decimal:4',
        'closed_at' => 'datetime',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(SalesBookEntry::class)->orderBy('operation_number', 'asc');
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
