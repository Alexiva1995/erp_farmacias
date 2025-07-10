<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonativeLog extends Model
{
    protected $fillable = ['donation_id', 'expired_log_id'];

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    public function expiredLog(): BelongsTo
    {
        return $this->belongsTo(ExpiredLog::class);
    }
}
