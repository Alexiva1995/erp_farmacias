<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CleaningActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'activity',
        'description',
        'frequency',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scope para filtrar por frecuencia
     */
    public function scopeByFrequency($query, $frequency)
    {
        return $query->where('frequency', $frequency);
    }

    /**
     * Scope para búsqueda
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('activity', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Obtener actividades ordenadas
     */
    public function scopeOrdered($query, $sortBy = 'activity', $order = 'asc')
    {
        return $query->orderBy($sortBy, $order);
    }
}
