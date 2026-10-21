<?php

declare(strict_types=1);

namespace App\Enums;

enum RetentionType: string
{
    case IVA = 'iva';
    case ISLR = 'islr';

    public function label(): string
    {
        return match ($this) {
            self::IVA => 'Retención de IVA',
            self::ISLR => 'Retención de ISLR',
        };
    }
}
