<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserCyclicQuota extends Model
{
    protected $table = 'user_cyclic_quotas';

    protected $fillable = [
        'user_id',
        'cycle_id',
        'quota_date',
        'quota_tier',
        'assigned_quantity',
        'assigned_product_ids',
    ];

    protected $casts = [
        'quota_date' => 'date',
        'quota_tier' => 'integer',
        'assigned_quantity' => 'integer',
        'assigned_product_ids' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cycle()
    {
        return $this->belongsTo(InventoryCycle::class, 'cycle_id');
    }
}
