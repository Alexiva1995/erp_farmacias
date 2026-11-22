<?php

declare(strict_types=1);

namespace App\Enums;

enum ReturnRefundMethodEnum: string
{
    case BALANCE = 'balance';
    case CASH = 'cash';
    case MOBILE_PAYMENT = 'mobile_payment';
    case TRANSFER = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::BALANCE => 'Saldo a favor',
            self::CASH => 'Efectivo',
            self::MOBILE_PAYMENT => 'Pago Móvil',
            self::TRANSFER => 'Transferencia',
        };
    }
}
