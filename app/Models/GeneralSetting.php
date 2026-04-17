<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralSetting extends Model
{
   protected $table = 'general_settings';
    protected $fillable = [
        'fiscal_mode',
        'special_taxpayer_status',
        'all_foreign_sales_spe',
        'rif',
        'address',
        'income_statement_reset_date',
    ];

    protected $casts = [
        'all_foreign_sales_spe' => 'boolean',
    ];
}
