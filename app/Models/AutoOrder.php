<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AutoOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'order_date',
        'total_items',
        'total_quantity',
        'total_amount',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function details()
    {
        return $this->hasMany(AutoOrderDetail::class, 'order_id');
    }
}
