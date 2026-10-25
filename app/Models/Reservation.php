<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = [
        'court_id',
        'dining_table_id',
        'guests_count',
        'special_requests',
        'deposit_amount_usd',
        'client_id',
        'identification',
        'date',
        'start_time',
        'end_time',
        'client_name',
        'client_whatsapp',
        'status',
        'request_weekly_fixed',
    ];

    protected $casts = [
        'date' => 'date',
        'request_weekly_fixed' => 'boolean',
    ];

    /**
     * Obtener la cancha asociada a la reserva.
     */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /**
     * Obtener el cliente asociado a la reserva.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }
}
