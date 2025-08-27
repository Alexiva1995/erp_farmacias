<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierConnectionStatus extends Model
{
    protected $fillable = [
        'supplier_id', 'user_id', 'status', 'message', 'count_product', 'count_invoice',
    ];

    public function supplier() {
        return $this->belongsTo(Supplier::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
