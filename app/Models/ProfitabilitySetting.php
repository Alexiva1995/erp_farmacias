<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfitabilitySetting extends Model
{
    protected $table = 'profitability_settings';

    protected $fillable = [
        'default_profitability_percentage',
        'shipping_cost',
        'packaging_cost',
        'expense_margin',
        'profit_margin',
        'tax_usa',
    ];
}
