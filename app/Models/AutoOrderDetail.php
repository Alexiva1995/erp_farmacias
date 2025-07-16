<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AutoOrderDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_suppliers_id',
        'quantity',
        'unit_cost',
        'subtotal',
    ];

    public function order()
    {
        return $this->belongsTo(AutoOrder::class, 'order_id');
    }

    public function productSupplier()
    {
        return $this->belongsTo(ProductSupplier::class, 'product_suppliers_id');
    }
}
