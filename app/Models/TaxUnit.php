<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaxUnit extends Model
{
    protected $fillable = [
        'value',
        'effective_date',
        'is_active',
        'notes'
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'effective_date' => 'date',
        'is_active' => 'boolean'
    ];

    /**
     * Obtiene la unidad tributaria activa actual
     */
    public static function getActive()
    {
        return self::where('is_active', true)
            ->orderBy('effective_date', 'desc')
            ->first();
    }

    /**
     * Desactiva todas las unidades tributarias
     */
    public static function deactivateAll()
    {
        self::where('is_active', true)->update(['is_active' => false]);
    }
}
