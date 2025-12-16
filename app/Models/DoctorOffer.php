<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorOffer extends Model
{
    protected $fillable = [
        'doctor_id',
        'start_date',
        'end_date',
        'is_active',
        'discount',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function scales()
    {
        return $this->hasMany(DoctorOfferScale::class);
    }
}
