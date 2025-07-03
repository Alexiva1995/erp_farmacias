<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxCredit extends Model
{
    protected $fillable = [
        'amount',
        'description',
    ];
}
