<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpirationOffer extends Model
{
    protected $fillable = [
        'months_to_expiration',
        'discount_percentage',
        'is_active',
    ];
}
