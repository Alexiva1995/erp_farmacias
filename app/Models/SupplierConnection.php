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
        "secondary_structure",
        "invoice_path",
        "invoice_structure",
        "last_connection",
    ];

    protected $casts = [
        "pasv" => "boolean",
        "has_header" => "boolean",
        "structure" => "array",
        "secondary_structure" => "array",
        "invoice_structure" => "array",
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
