<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPack extends Model
{
    protected $fillable = [
        'name',
        'pack_config',
    ];

    protected $casts = [
        'pack_config' => 'array',
    ];

    /**
     * @property-read array $products_with_quantity
     */
    protected $appends = ['products_with_quantity'];

    public function getProductsWithQuantityAttribute()
    {
        return $this->pack_config ?? [];
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class, 'pack_id');
    }
}
