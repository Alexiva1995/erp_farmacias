<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VatReport extends Model
{
    protected $fillable = [
        'report_month',
        'total_vat_paid',
        'payment_file_path',
        'vat_file_path',
    ];
}
