<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    use HasFactory;

    protected $table = 'quotations';

   
    protected $fillable = [
        'currency',
        'tax_exempt',
        'vat',
        'total',
        'created_by',
    ];

   
     public function products()
    {
        return $this->hasMany(QuotationProduct::class);
    }


}
