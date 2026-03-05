<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Retention extends Model
{
    protected $fillable = [
        'supplier_id',
        'number',
        'date',
        'total_taxable_base',
        'total_tax_amount',
        'total_withheld_amount',
        'retention_percentage',
    ];

    protected $casts = [
        'date' => 'date',
        'total_taxable_base' => 'decimal:2',
        'total_tax_amount' => 'decimal:2',
        'total_withheld_amount' => 'decimal:2',
        'retention_percentage' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
