<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payslip extends Model
{
    protected $fillable = ["payslip_date", "name", "status", "total", "exchange_rate"];

    public function details()
    {
        return $this->hasMany(PayslipDetails::class);
    }
}
