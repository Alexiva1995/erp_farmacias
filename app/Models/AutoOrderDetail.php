<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutoOrderDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ["order_id", "product_id", "product_suppliers_id", "quantity", "unit_cost", "subtotal", 'received', 'status'];

    public function order()
    {
        return $this->belongsTo(AutoOrder::class, "order_id");
    }

    public function productSupplier()
    {
        return $this->belongsTo(ProductSupplier::class, "product_suppliers_id");
    }
}
