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

    /**
     * Mapear desde las etiquetas antiguas o entrada del usuario a los valores del Enum.
     */
    public static function fromOldLabel(string $label): self
    {
        return match ($label) {
            'Efectivo' => self::CASH,
            'Tarjeta' => self::CARD,
            'Transferencia' => self::TRANSFER,
            'Pago Móvil' => self::MOBILE,
            'Binance' => self::BINANCE,
            'PayPal' => self::PAYPAL,
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
        };
    }
}
