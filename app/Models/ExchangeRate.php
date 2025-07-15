<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    //

    protected $table = 'exchange_rates';

    /**
     * Los atributos que son asignables masivamente.
     */
    protected $fillable = [
        'currency_code',
        'rate',
        'source'
    ];
}
