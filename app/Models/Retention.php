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
    ];

    /**
     * Accesor tolerante para el Enum RetentionType (evita fatal error si la BD contiene '' o nulos)
     */
    public function getTypeAttribute($value): ?\App\Enums\RetentionType
    {
        if (empty($value)) {
            return \App\Enums\RetentionType::IVA;
        }

        return \App\Enums\RetentionType::tryFrom(strtolower((string) $value)) ?? \App\Enums\RetentionType::IVA;
    }

    /**
     * Mutador tolerante para el Enum RetentionType
     */
    public function setTypeAttribute($value): void
    {
        $this->attributes['type'] = $value instanceof \App\Enums\RetentionType
            ? $value->value
            : (empty($value) ? 'iva' : strtolower((string) $value));
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'retention_id');
    }

    public function islrInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'islr_retention_id');
    }
}
