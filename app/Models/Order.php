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
        'money_returns' => 'decimal:2',
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
        // Recalcular promociones generales antes de sumar los totales
        app(\App\Services\Order\OrderActionService::class)->applyGeneralPromotions($this);

        // Sumamos (precio unitario * cantidad) de los detalles vinculados incluyendo IVA en productos o platos gravados
        $details = $this->details()->with(['product:id,iva', 'dish:id,iva,tax_rate'])->get();
        $newTotal = 0;
        $totalCost = 0;

        foreach ($details as $detail) {
            $qty = (float) ($detail->quantity ?? 0);
            $price = (float) ($detail->price ?? 0);
            $unitCost = (float) ($detail->unit_cost ?? 0);
            
            $isTaxable = false;
            $taxRatePct = 16.0;

            if ($detail->product_type === 'dish') {
                if ($detail->dish && (bool) $detail->dish->iva) {
                    $isTaxable = true;
                    $taxRatePct = (float) ($detail->dish->tax_rate ?? 16.0);
                }
            } elseif ($detail->product_type !== 'court') {
                if ((int) ($detail->product?->iva ?? 0) === 1) {
                    $isTaxable = true;
                    $taxRatePct = 16.0;
                }
            }

            $lineSubtotal = $price * $qty;
            $lineIva = $isTaxable ? ($lineSubtotal * ($taxRatePct / 100)) : 0;

            $newTotal += ($lineSubtotal + $lineIva);
            $totalCost += ($unitCost * $qty);
        }

        // Redondeo específico para COP al total (no por unidad) para evitar discrepancias
        if (strtoupper($this->currency) === 'COP') {
            $newTotal = ceil($newTotal / 100) * 100;
        } else {
            $newTotal = round($newTotal, 2);
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
