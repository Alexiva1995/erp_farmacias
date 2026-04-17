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
    ];

    protected $appends = ['total_bs_in_usd', 'total_cop_in_usd'];

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
        // 1. Totales de Venta (Solo Órdenes - Rendimiento de la jornada)
        // total_cop ahora representa la venta neta esperada en pesos por órdenes facturadas en esa moneda.
        // NO restamos cop_conversion aquí porque cop_conversion puede incluir vueltos de órdenes en USD.
        // La lógica de negocio dicta que cop_cash debe ser el neto de (Pagos COP recibidos - Vueltos de órdenes COP).
        $this->total_cop = ($this->cop_cash + $this->cop_transfer);
        $this->total_usd = ($this->usd_cash + $this->usd_transfer + $this->usd_paypal + $this->usd_binance + $this->usd_balance + $this->usd_conversion);
        $this->total_bs  = ($this->bs_cash + $this->bs_transfer + $this->bs_mobile + $this->bs_card_debito + $this->bs_card_credit);

        // 2. Efectivo Neto Real a Entregar (Físico: Ventas Cash + Abonos Cash - Vueltos de cambio cruzado)
        // La resta de conversion ya se realizó en OrderActionService sobre usd_cash/cop_cash
        $this->cop_delivered = $this->cop_cash + $this->cop_cash_payment_credit;
        $this->usd_delivered = $this->usd_cash + $this->usd_cash_payment_credit;
        $this->bs_delivered  = $this->bs_cash + $this->bs_cash_payment_credit;

        // 3. Venta Bruta (USD equivalente) - Refleja el rendimiento consolidado incluyendo créditos otorgados
        $copInUsd = $this->getServiceExchangeRate('COP') > 0 ? ($this->total_cop / $this->getServiceExchangeRate('COP')) : 0;
        $bsInUsd  = $this->getServiceExchangeRate('BS')  > 0 ? ($this->total_bs  / $this->getServiceExchangeRate('BS'))  : 0;
        $this->total_sales = round($this->total_usd + $this->usd_credit + $copInUsd + $bsInUsd, 2);

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
}
