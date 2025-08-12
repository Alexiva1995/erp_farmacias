<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierConnection extends Model
{
    protected $fillable = [
        'supplier_id',
        'type',
        'host',
        'port',
        'username',
        'password',
        'path',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
