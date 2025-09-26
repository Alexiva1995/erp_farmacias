<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = ["name", "last_name", "identification", "is_active", "photo", "rif", "residence_letter", "cv"];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
