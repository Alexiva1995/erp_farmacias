<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierScore extends Model
{
    protected $fillable = [
        'supplier_id',
        'score',
        'breakdown',
        'evaluated_on',
    ];

    protected $casts = [
        'breakdown' => 'array',
        'evaluated_on' => 'date',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}