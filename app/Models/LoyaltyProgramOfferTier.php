<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyProgramOfferTier extends Model
{
    protected $fillable = [
        'loyalty_program_offer_id',
        'min_volume',
        'max_volume',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgramOffer::class, 'loyalty_program_offer_id');
    }
}
