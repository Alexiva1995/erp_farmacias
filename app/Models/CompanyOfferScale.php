<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompanyOfferScale extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_offer_id',
        'min_volume',
        'max_volume',
        'discount_percentage',
    ];

    public function offer()
    {
        return $this->belongsTo(CompanyOffer::class, 'company_offer_id');
    }
}
