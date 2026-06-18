<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedScheduleException extends Model
{
    protected $fillable = [
        'fixed_schedule_id',
        'date'
    ];

    public function fixedSchedule()
    {
        return $this->belongsTo(FixedSchedule::class);
    }
}
