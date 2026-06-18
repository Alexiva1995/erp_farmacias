<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = [
        'court_id',
        'date',
        'start_time',
        'end_time',
        'client_name',
        'client_whatsapp',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Obtener la cancha asociada a la reserva.
     */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}
