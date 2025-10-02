<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryConcept extends Model
{
    protected $fillable = [
        'name',
        'type',
        'frequency'
    ];

    public function salaryDetails()
    {
        return $this->hasMany(UsersSalaryDetails::class);
    }
}
