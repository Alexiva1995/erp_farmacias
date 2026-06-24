<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingVisit extends Model
{
    protected $fillable = [
        'ip_address',
        'user_agent',
        'converted',
        'reservation_id',
    ];

    protected $casts = [
        'converted' => 'boolean',
    ];

    /**
     * Relación con la reservación si se concretó.
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
