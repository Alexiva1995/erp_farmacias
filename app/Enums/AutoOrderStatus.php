<?php

namespace App\Enums;

enum AutoOrderStatus: int
{
    case PENDING = 0;
    case SENT = 1;
    case COMPLETED = 2;

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::SENT => 'Enviado',
            self::COMPLETED => 'Completado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::SENT => 'info',
            self::COMPLETED => 'success',
        };
    }
}
