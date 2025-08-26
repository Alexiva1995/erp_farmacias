<?php

namespace App;

enum AutoOrderDetailStatus: int
{
    case PENDING = 0;
    case ARRIVED = 1;
    case NOT_ARRIVED = 2;

    public function label(): string
    {
        return match ($this) {
            self::PENDING => "Pendiente",
            self::ARRIVED => "Llegó",
            self::NOT_ARRIVED => "No llegó",
        };
    }
}
