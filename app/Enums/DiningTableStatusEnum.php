<?php

declare(strict_types=1);

namespace App\Enums;

enum DiningTableStatusEnum: string
{
    case AVAILABLE = 'available';
    case OCCUPIED = 'occupied';
    case BILL_REQUESTED = 'bill_requested';
    case RESERVED = 'reserved';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Libre',
            self::OCCUPIED => 'Ocupada',
            self::BILL_REQUESTED => 'Cuenta Solicitada',
            self::RESERVED => 'Reservada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AVAILABLE => 'success',
            self::OCCUPIED => 'warning',
            self::BILL_REQUESTED => 'info',
            self::RESERVED => 'error',
        };
    }
}
