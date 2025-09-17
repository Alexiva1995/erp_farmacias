<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductSupplier extends Model
{
    use HasFactory;

    protected $fillable = [
        "product_id",
        "supplier_id",
        "barcode_match",
        "name",
        "laboratory",
        "expiration",
        "unit_cost",
        "unit_cost_usd",
        "connection_date",
        "cod_supplier",
        "quantity",
        "unit_cost_with_discount",
        "unit_cost_usd_with_discount",
        "active_ingredient"
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function autoOrderDetails()
    {
        return $this->hasMany(AutoOrderDetail::class, "product_suppliers_id");
    }
}
