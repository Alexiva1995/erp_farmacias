<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyProgramOffer extends Model
{
    protected $fillable = [
        'program_name',
        'description',
        'discount_percentage',
        'start_date',
        'end_date',
        'is_active',
    ];

    public function tiers()
    {
        return $this->hasMany(LoyaltyProgramOfferTier::class, 'loyalty_program_offer_id');
    }
}
