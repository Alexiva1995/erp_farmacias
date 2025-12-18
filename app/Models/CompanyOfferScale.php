<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompanyOfferScale extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_offer_id',
        'min_amount',
        'max_amount',
        'discount_percentage',
    ];

    protected $casts = [
    'min_amount' => 'decimal:2',
    'max_amount' => 'decimal:2',
];

    /* Relacion con CompanyOffer*/
    public function offer()
    {
        return $this->belongsTo(CompanyOffer::class, 'company_offer_id');
    }
}
