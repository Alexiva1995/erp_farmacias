<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedSchedule extends Model
{
    protected $fillable = [
        'court_id',
        'day_of_week',
        'start_time',
        'end_time',
        'client_name',
        'client_whatsapp',
    ];

    /**
     * Obtener la cancha asociada a este horario fijo.
     */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function exceptions()
    {
        return $this->hasMany(FixedScheduleException::class);
    }
}
