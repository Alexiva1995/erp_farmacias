<?php

namespace App\Enums;

enum ExpensePaymentMethod: string
{
    case CASH = 'CASH';
    case CARD = 'CARD';
    case TRANSFER = 'TRANSFER';
    case MOBILE = 'MOBILE';
    case BINANCE = 'BINANCE';
    case PAYPAL = 'PAYPAL';
    case CAMBISTA = 'CAMBISTA';

    /**
     * Mapear desde las etiquetas antiguas o entrada del usuario a los valores del Enum.
     */
    public static function fromOldLabel(string $label): self
    {
        return match (mb_strtolower(trim($label))) {
            'efectivo' => self::CASH,
            'tarjeta' => self::CARD,
            'transferencia' => self::TRANSFER,
            'pago móvil', 'pago movil' => self::MOBILE,
            'binance' => self::BINANCE,
            'paypal' => self::PAYPAL,
            'cambista' => self::CAMBISTA,
            default => self::CASH,
        };
    }

    /**
     * Obtener la etiqueta en español.
     */
    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Efectivo',
            self::CARD => 'Tarjeta',
            self::TRANSFER => 'Transferencia',
            self::MOBILE => 'Pago Móvil',
            self::BINANCE => 'Binance',
            self::PAYPAL => 'PayPal',
            self::CAMBISTA => 'Cambista',
        };
    }
}
