<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DonativeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'donation_batch_uuid',
        'institution_name',
        'expired_log_id',
    ];

    public function expiredLog()
    {
        return $this->belongsTo(ExpiredLog::class);
    }
}
