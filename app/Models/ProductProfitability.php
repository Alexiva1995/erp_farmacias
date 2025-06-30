<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductProfitability extends Model
{
    //

    protected $table = 'product_profitability';

    /**
     * Los atributos que son asignables masivamente.
     */
    protected $fillable = [
        'product_id',
        'profitability_percentage',
        'is_locked'
    ];
}
