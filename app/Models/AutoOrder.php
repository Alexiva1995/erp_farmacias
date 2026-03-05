<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutoOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ["supplier_id", "order_date", "total_items", "total_quantity", "total_amount", "status", "sent_at", "tentative_delivery_date"];

    protected $casts = [
        "status" => "boolean",
        "sent_at" => "datetime",
        "tentative_delivery_date" => "date",
    ];

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
