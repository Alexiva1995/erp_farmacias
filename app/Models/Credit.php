<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Credit extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'order_id',
        'credit_amount',
        'pending_amount',
        'credit_date',
        'status',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
