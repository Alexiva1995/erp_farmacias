<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\DB;

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
        'total_cost',
        'taxable_base',
        'spe_surcharge_rate',
        'spe_surcharge_amount',
        'order_date',
        'status',
        'has_multiple_currencies',
        'payment_methods',
        'usd_conversion',
        'total_amount_usd'
    ];

    protected $casts = [
        'payment_methods' => 'array',
        'has_multiple_currencies' => 'boolean',
        'order_date' => 'datetime',
        'taxable_base' => 'decimal:2',
        'spe_surcharge_rate' => 'decimal:2',
        'spe_surcharge_amount' => 'decimal:2',
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


    /**
     * Accesor para limpiar pagos corruptos en tiempo de ejecución
     */
    public function getPaymentMethodsAttribute($value)
    {
        $data = is_array($value) ? $value : json_decode($value, true);

        if (empty($data) || !is_array($data)) {
            return [];
        }
        return array_values(array_filter($data, function ($payment) {
            return is_array($payment) &&
                isset($payment['amount']) &&
                !is_null($payment['amount']) &&
                $payment['amount'] > 0;
        }));
    }


    public function updateTotals()
    {
        // Sumamos (precio unitario * cantidad) de los detalles vinculados
        $newTotal = $this->details()->select(DB::raw('SUM(price * quantity) as total'))->value('total') ?? 0;
        $totalCost = $this->details()->select(DB::raw('SUM(unit_cost * quantity) as total'))->value('total') ?? 0;

        // Redondeo específico para COP al total (no por unidad) para evitar discrepancias
        if (strtoupper($this->currency) === 'COP') {
            $newTotal = ceil($newTotal / 100) * 100;
        }

        $totalUsd = 0;
        if (strtoupper($this->currency) === 'USD') {
            $totalUsd = $newTotal;
        } else {
            // Buscamos la tasa de cambio más reciente para la moneda de la orden
            $exchangeRate = ExchangeRate::where('currency_code', $this->currency)
                ->latest()
                ->first();
            if ($exchangeRate && $exchangeRate->rate > 0) {
                $totalUsd = $newTotal / $exchangeRate->rate;
            }
        }

        $this->update([
            'total_amount' => $newTotal,
            'total_amount_usd' => $totalUsd,
            'total_cost' => $totalCost,
        ]);
    }
}
