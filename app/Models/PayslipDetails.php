<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayslipDetails extends Model
{
    protected $fillable = ["amount", "payslip_id", "users_salary_details_id"];

    public function payslip()
    {
        return $this->belongsTo(Payslip::class);
    }

    public function salary()
    {
        return $this->belongsTo(UsersSalaryDetails::class, 'users_salary_details_id', 'id');
    }
}
