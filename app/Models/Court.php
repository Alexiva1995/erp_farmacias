<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Court extends Model
{
    protected $fillable = ['name', 'price'];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    /**
     * Obtener los horarios fijos de la cancha.
     */
    public function fixedSchedules(): HasMany
    {
        return $this->hasMany(FixedSchedule::class);
    }

    /**
     * Obtener las reservas de la cancha.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
