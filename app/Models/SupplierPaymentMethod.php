<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierPaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'type',
        'days',
    ];

    /**
     * Relación con el proveedor
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
