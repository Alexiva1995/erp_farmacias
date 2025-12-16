<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnEntry extends Model
{
    protected $table = 'returns';

    const REJECTED = 'Rejected';
    const APPROVED = 'Approved';

    protected $fillable = [
        'order_id',
        'generated_by_id',
        'product_id',
        'quantity',
        'amount_refunded',
        'return_date',
        'status',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
