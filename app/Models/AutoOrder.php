<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Enums\AutoOrderStatus;

class AutoOrder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ["supplier_id", "order_date", "total_items", "total_quantity", "total_amount", "status", "sent_at", "tentative_delivery_date", "hash_token"];

    protected $casts = [
        "status" => AutoOrderStatus::class,
        "sent_at" => "datetime",
        "tentative_delivery_date" => "date",
    ];

    protected static function booted()
    {
        static::creating(function ($order) {
            if (empty($order->hash_token)) {
                $order->hash_token = md5(uniqid((string)rand(), true)) . \Illuminate\Support\Str::random(16);
            }
        });
    }

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
