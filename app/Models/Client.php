<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'identification',
        'identification_type',
        'name',
        'last_name',
        'email',
        'phone',
        'address',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function credits()
    {
        return $this->hasMany(Credit::class);
    }
}
