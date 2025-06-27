<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Doctor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'identification',
        'address',
    ];

    public function offers()
    {
        return $this->hasMany(DoctorOffer::class);
    }
}
