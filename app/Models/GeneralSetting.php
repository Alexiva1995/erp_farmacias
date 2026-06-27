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
        'app_name',
        'app_rif',
        'app_logo',
        'app_favicon',
        'primary_color',
        'secondary_color',
        'tertiary_color',
        'footer_text',
        'blind_cash_closure',
        'business_type',
        'default_currency',
        'ecommerce_menu',
        'hero_title',
        'hero_subtitle',
        'hero_tagline',
        'hero_image',
        'hero_button_text',
        'section2_title',
        'section2_subtitle',
        'section2_tagline',
        'section2_image',
        'section2_button_text',
        'section3_title',
        'section3_subtitle',
        'section3_tagline',
        'section3_image',
        'section3_button_text',
    ];

    protected $casts = [
        'all_foreign_sales_spe' => 'boolean',
        'blind_cash_closure' => 'boolean',
        'ecommerce_menu' => 'array',
    ];
}
