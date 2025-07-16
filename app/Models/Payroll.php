<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    protected $fillable = [
        'payroll_date',
        'amount_total',
        'amount_salary',
        'amount_feeding',
        'amount_transport',
        'amount_medical',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function details()
    {
        return $this->hasMany(PayrollDetail::class);
    }
}
