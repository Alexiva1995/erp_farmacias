<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseBookPeriodType;
use App\Enums\PurchaseBookStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VatRetentionBook extends Model
{
    protected $table = 'vat_retention_books';

    protected $fillable = [
        'period_type',
        'year',
        'month',
        'fortnight',
        'start_date',
        'end_date',
        'status',
        'total_documents',
        'total_document_amount',
        'total_taxable_base',
        'total_tax_amount',
        'total_withheld_amount',
        'total_exempt_amount',
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
        'total_documents' => 'integer',
        'total_document_amount' => 'decimal:2',
        'total_taxable_base' => 'decimal:2',
        'total_tax_amount' => 'decimal:2',
        'total_withheld_amount' => 'decimal:2',
        'total_exempt_amount' => 'decimal:2',
        'closed_at' => 'datetime',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(VatRetentionBookEntry::class, 'vat_retention_book_id');
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

    public function getPeriodLabelAttribute(): string
    {
        $monthName = \Carbon\Carbon::create($this->year, $this->month, 1)->locale('es')->monthName;
        $monthName = mb_strtoupper($monthName, 'UTF-8');

        $periodTypeValue = is_object($this->period_type) ? $this->period_type->value : (string) $this->period_type;

        if ($periodTypeValue === 'fortnightly') {
            $fortnightText = $this->fortnight === 1 ? '1ra Quincena' : '2da Quincena';
            return "{$fortnightText} {$monthName} {$this->year}";
        }

        return "Mes de {$monthName} {$this->year}";
    }
}
