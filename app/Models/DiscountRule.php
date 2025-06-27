<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DiscountRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_laboratory_id',
        'min_amount',
        'min_quantity',
        'discount_percentage',
    ];

    public function supplierLaboratory()
    {
        return $this->belongsTo(SupplierLaboratory::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'discount_rule_id');
    }
}
