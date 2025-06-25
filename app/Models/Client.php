<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    //

    const IDENTIFICATION_TYPE_VENEZOLANO = "V-";
    const IDENTIFICATION_TYPE_GOBIERNO = "G-";
    const IDENTIFICATION_TYPE_EXTRANJERO = "E-";
    const IDENTIFICATION_TYPE_JURIDICO = "J-";


    use SoftDeletes;

    protected $fillable = [
        'identification',
        'identification_type',
        'name',
        'last_name',
        'email',
        'phone',
        'address',
        'birthdate',
        'company_id'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con la compañía
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Accesor para nombre completo
     */
    public function getFullNameAttribute()
    {
        return "{$this->name} {$this->last_name}";
    }
}
