<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCycle extends Model
{
    protected $fillable = [
        'start_date',
        'end_date',
        'status',
    ];

    public function expirations()
    {
        return $this->hasMany(Expiration::class);
    }

    public function closures()
    {
        return $this->hasMany(InventoryClosure::class, 'cycle_id');
    }

    public function productCounts()
    {
        return $this->hasMany(ProductCount::class, 'cycle_id');
    }
}
