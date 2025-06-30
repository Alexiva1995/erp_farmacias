<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryClosure extends Model
{
    protected $fillable = [
        'cycle_id',
        'missing_units',
        'leftover_units',
        'missing_amount',
        'leftover_amount',
        'total_amount',
        'total_units',
        'closed_at',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(InventoryCycle::class, 'cycle_id');
    }
}
