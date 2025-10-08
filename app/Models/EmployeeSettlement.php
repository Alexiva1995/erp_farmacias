<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeSettlement extends Model
{
    protected $fillable = [
        'currency',
        'social_benefits_days',
        'social_benefits_amount',
        'vacation_voucher_days',
        'vacation_voucher_amount',
        'vacation_bonus_voucher_days',
        'vacation_bonus_voucher_amount',
        'earnings_voucher_days',
        'earnings_voucher_amount',
        'total_settlement',
        'vacation_voucher_deduction',
        'vacation_bonus_voucher_deduction',
        'earnings_voucher_deduction',
        'total_deduction',
        'subtotal',
        'percentage',
        'total',
        'employee_id',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
