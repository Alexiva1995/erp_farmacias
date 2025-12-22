<?php

namespace App;

enum TransactionType: string
{
    /**
     * Tipos
     * 
     * BS (Efectivo, Tarjeta, Pago movil, Transferencia)
     * COP (Efectivo, Transferencia)
     * USD (Efectivo, binance, paypal, credito)
     */
    case CASH = 'CASH';
    case CARD = 'CARD';
    case TRANSFER = 'TRANSFER';
    case MOBILE = 'MOBILE';
    case BINANCE = 'BINANCE';
    case PAYPAL = 'PAYPAL';
    case CREDIT = 'CREDIT';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Efectivo',
            self::CARD => 'Tarjeta',
            self::TRANSFER => 'Transferencia',
            self::MOBILE => 'Pago móvil',
            self::BINANCE => 'Binance',
            self::PAYPAL => 'PayPal',
            self::CREDIT => 'Crédito',
        };
    }
}
