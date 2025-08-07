<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorOfferScale extends Model
{
    protected $fillable = [
        'doctor_offer_id',
        'min_volume',
        'max_volume',
        'discount_percentage',
    ];

    public function doctorOffer(): BelongsTo
    {
        return $this->belongsTo(DoctorOffer::class);
    }
}
