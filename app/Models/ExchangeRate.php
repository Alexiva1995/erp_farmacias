<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    // Define los atributos que pueden ser asignados masivamente.
    // Esto es crucial para la seguridad y para permitir la creación/actualización de registros
    // utilizando métodos como `create()` o `update()` con un array de datos.
    protected $fillable = [
        'currency_code', // Código de la moneda (ej. USD, EUR)
        'rate',          // Tasa de cambio
        'source',        // Fuente de la tasa de cambio (ej. API externa, banco)
    ];
}
