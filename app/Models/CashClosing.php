<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Services\Resources\ResourceService;

class CashClosing extends Model
{
    use HasFactory;

    protected $table = 'cash_closing';

    const OPEN = 'open';
    const CLOSED = 'closed';

    protected $fillable = [
        'seller_id',
        'closing_date',
        'status',
        'total_sales',
        'total_usd',
        'total_cop',
        'total_bs',
        'bs_card_debito',
        'bs_card_credit',
        'bs_cash',
        'bs_transfer',
        'bs_mobile',
        'cop_cash',
        'cop_transfer',
        'cop_conversion',
        'cop_spare',
        'usd_transfer',
        'usd_cash',
        'usd_paypal',
        'usd_binance',
        'usd_conversion',
        'usd_credit',
        'usd_balance',
        'usd_delivered',
        'cop_delivered',
        'bs_delivered',
        'bs_card_payment_credit',
        'bs_cash_payment_credit',
        'bs_transfer_payment_credit',
        'bs_mobile_payment_credit',
        'cop_cash_payment_credit',
        'cop_transfer_payment_credit',
        'cop_conversion_payment_credit',
        'usd_transfer_payment_credit',
        'usd_cash_payment_credit',
        'usd_paypal_payment_credit',
        'usd_binance_payment_credit',
        'daily_closure_id',
        'declared_cop',
        'declared_cop_transfer',
        'declared_usd',
        'declared_credit',
        'declared_bs_mobile',
        'declared_bs_card',
        'blind_mismatches',
        'blind_note',
        'diff_cop',
        'diff_cop_transfer',
        'diff_usd',
        'diff_credit',
        'diff_bs_mobile',
        'diff_bs_card',
    ];

    protected $appends = ['total_bs_in_usd', 'total_cop_in_usd', 'blind_cash_closure', 'exchange_rate', 'cop_exchange_rate'];

    public function exchangeRate(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->getServiceExchangeRate('BS'),
        );
    }

    public function copExchangeRate(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->getServiceExchangeRate('COP'),
        );
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function cashFlows()
    {
        return $this->hasMany(CashFlow::class, 'cash_closing_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'cash_closing_id');
    }

      public function dailyClosure()
    {
        return $this->belongsTo(DailyCashClosure::class);
    }

    protected function getServiceExchangeRate(string $currencyCode): float
    {
        $resourceService = app(ResourceService::class);
        return $resourceService->getExchangeRate($currencyCode);
    }

    /**
     * Recalcula todos los totales del cierre de caja.
     * Separa el total de ventas (rendimiento) del efectivo neto a entregar (físico).
     */
    public function recalculateTotals()
    {
        // 1. Totales de Venta (Rendimiento Real - Basado en Órdenes completadas + Ajustes)
        $this->total_cop = $this->orders()->where('currency', 'COP')->where('status', 'Completed')->sum('total_amount') + ($this->cop_spare ?? 0);
        $this->total_usd = $this->orders()->where('currency', 'USD')->where('status', 'Completed')->sum('total_amount');
        $this->total_bs  = $this->orders()->where('currency', 'BS')->where('status', 'Completed')->sum('total_amount');

        // 2. Efectivo Neto Real a Entregar (Físico: Ventas Cash + Abonos Cash - Vueltos - Ajustes)
        $this->cop_delivered = $this->cop_cash + $this->cop_cash_payment_credit + ($this->cop_spare ?? 0);
        $this->usd_delivered = $this->usd_cash + $this->usd_cash_payment_credit;
        $this->bs_delivered  = $this->bs_cash + $this->bs_cash_payment_credit;

        // 3. Venta Bruta (USD equivalente) - Refleja el rendimiento consolidado incluyendo créditos otorgados
        $copInUsd = $this->getServiceExchangeRate('COP') > 0 ? ($this->total_cop / $this->getServiceExchangeRate('COP')) : 0;
        $bsInUsd  = $this->getServiceExchangeRate('BS')  > 0 ? ($this->total_bs  / $this->getServiceExchangeRate('BS'))  : 0;
        $this->total_sales = round($this->total_usd + $copInUsd + $bsInUsd, 2);

        $this->save();
        return $this;
    }
    /**
     * Accesor para el TOTAL en Bolívares (BS). Coventido EN USD
     */
    protected function totalBsInUsd(): Attribute
    {
        return Attribute::make(
            get: fn() => round($this->total_bs / $this->getServiceExchangeRate('BS'), 2),
        );
    }

    /**
     * Accesor para el precio en Pesos Colombianos (COP). Coventido EN USD
     */
    protected function totalCopInUsd(): Attribute
    {
        return Attribute::make(
            get: fn() => round($this->total_cop / $this->getServiceExchangeRate('COP'), 2),
        );
    }

    /**
     * Accesor para saber si está activo el cierre ciego
     */
    protected function blindCashClosure(): Attribute
    {
        return Attribute::make(
            get: fn() => !empty(\App\Models\GeneralSetting::first()?->blind_cash_closure),
        );
    }
}
