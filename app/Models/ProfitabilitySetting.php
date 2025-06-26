<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfitabilitySetting extends Model
{
    protected $fillable = [
        'default_profitability_percentage',
    ];
}
