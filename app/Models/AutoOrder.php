<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutoOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ["supplier_id", "order_date", "total_items", "total_quantity", "total_amount", "status"];

    protected $casts = ["status" => "boolean"];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function details()
    {
        return $this->hasMany(AutoOrderDetail::class, "order_id");
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, "auto_order_id");
    }

    public function invoiceDetails()
    {
        return $this->hasMany(InvoiceDetail::class);
    }
}
