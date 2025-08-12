<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SupplierDiscount extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'name',
        'discount_percentage',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
