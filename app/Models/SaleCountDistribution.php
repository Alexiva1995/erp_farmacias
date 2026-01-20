<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleCountDistribution extends Model
{
    use HasFactory;

    protected $table = 'sale_count_distributions';

    protected $fillable = [
        'sale_count_id',
        'product_lot_id',
        'quantity',
    ];

        public function saleCount()
    {
        return $this->belongsTo(SaleCount::class, 'sale_count_id');
    }

        public function productLot()
    {
        return $this->belongsTo(ProductLot::class, 'product_lot_id');
    }
}
