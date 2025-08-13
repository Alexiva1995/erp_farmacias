<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierConnection extends Model
{
    protected $fillable = [
        "supplier_id",
        "type",
        "host",
        "port",
        "username",
        "password",
        "path",
        "pasv",
        "has_header",
        "structure",
    ];

    protected $casts = [
        "pasv" => "boolean",
        "has_header" => "boolean",
        "structure" => "array",
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
