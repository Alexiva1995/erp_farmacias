<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    //

    const COMPANY = "Empresa";
    const CLINIC = "Clinica";


    use SoftDeletes;

    protected $fillable = [
        "id",
        "name",
        "identification",
        "address",
        "type_company",
    ];

    public function clients()
    {
        return $this->hasMany(Client::class);
    }
}
