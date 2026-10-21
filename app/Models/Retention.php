<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Retention extends Model
{
    protected $fillable = [
        'supplier_id',
        'type',
        'number',
        'date',
        'total_taxable_base',
        'total_tax_amount',
        'total_withheld_amount',
        'retention_percentage',
        'concept_description',
        'subtrahend',
    ];

    protected $casts = [
        'date' => 'date',
        'total_taxable_base' => 'decimal:2',
        'total_tax_amount' => 'decimal:2',
        'total_withheld_amount' => 'decimal:2',
        'retention_percentage' => 'decimal:2',
        'subtrahend' => 'decimal:2',
        'type' => \App\Enums\RetentionType::class,
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function islrInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'islr_retention_id');
    }
}
