<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupplierLaboratory extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'laboratory_id',
        'phone',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function laboratory()
    {
        return $this->belongsTo(Laboratory::class);
    }

    public function discountRules()
    {
        return $this->hasMany(DiscountRule::class);
    }
}
