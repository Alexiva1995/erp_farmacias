<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SaleCount extends Model
{
     use HasFactory;
     protected $table = 'sales_counts';
     protected $fillable = [
        'cycle_id',
        'product_id',
        'counted_quantity',
        'system_quantity',
        'discrepancy',
        'type',
        'status',
        'user_id',
        'supervisor_id',
        'points_earned',
        'error_penalty_type',
        'penalty_points',
    ];

    protected $casts = [
        'counted_quantity' => 'float',
        'system_quantity' => 'float',
        'discrepancy' => 'float',
        'points_earned' => 'integer',
        'penalty_points' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

        public function cycle()
    {
        return $this->belongsTo(InventoryCycle::class, 'cycle_id');
    }

        public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

        public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

       public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * Un conteo puede tener muchas distribuciones en diferentes lotes.
     */
    public function distributions()
    {
        return $this->hasMany(SaleCountDistribution::class, 'sale_count_id');
    }
}
