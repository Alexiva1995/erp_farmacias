<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfitabilitySettings extends Model
{
    //

    protected $table = 'profitability_settings';

    /**
     * Los atributos que son asignables masivamente.
     */
    protected $fillable = [
        'default_profitability_percentage',
        'shipping_cost',
        'packaging_cost',
        'expense_margin',
        'profit_margin',
    ];
}
