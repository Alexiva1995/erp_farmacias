<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompanyOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'start_date',
        'end_date',
        'is_active',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /* Relacion con las escalas*/
    public function scales()
    {
        return $this->hasMany(CompanyOfferScale::class);
    }
}
