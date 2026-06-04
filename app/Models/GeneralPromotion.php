<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralPromotion extends Model
{
    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'type',
        'fixed_price',
        'is_active',
        'categories',
    ];

    // Casteo de tipos de datos
    protected $casts = [
        'is_active' => 'boolean',
        'categories' => 'array',
        'fixed_price' => 'decimal:2',
    ];
}
