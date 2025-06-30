<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrescriptionOffer extends Model
{
    protected $fillable = [
        'discount_percentage',
        'start_date',
        'end_date',
        'is_active',
    ];

    public $timestamps = true;
}
