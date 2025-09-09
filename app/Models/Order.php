<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    const PENDING = 'Pending';
    const ABANDONED = 'Abandoned'; 
    const RESERVED = 'Reserved';       
    const CLOSED = 'closed';
    const CANCELLED = 'Cancelled';
    const COMPLETED = 'Completed';

    protected $fillable = [
        'client_id',
        'seller_id',
        'cash_closing_id',
        'total_amount',
        'money_returns',
        'currency',
        'order_date',
        'status',
        'has_multiple_currencies',
        'payment_methods',
    ];

    protected $casts = [
        'payment_methods' => 'array',
        'has_multiple_currencies' => 'boolean',
        'order_date' => 'datetime',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class);
    }

    public function cashClosing()
    {
        return $this->belongsTo(CashClosing::class);
    }

    public function credit()
    {
        return $this->hasOne(Credit::class);
    }

    public function fiscalHistory()
    {
        return $this->hasOne(FiscalHistory::class);
    }

    public function returns()
    {
        return $this->hasMany(ReturnEntry::class);
    }

    public function details()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function inventoryMovements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function psychotropicControls()
    {
        return $this->hasMany(PsychotropicControl::class);
    }
}
