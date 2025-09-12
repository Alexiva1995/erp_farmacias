<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersSalaryDetails extends Model
{
    protected $fillable = [
        'user_id',
        'salary_concept_id',
        "amount"
    ];

    public function concept()
    {
        return $this->belongsTo(SalaryConcept::class, 'salary_concept_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
