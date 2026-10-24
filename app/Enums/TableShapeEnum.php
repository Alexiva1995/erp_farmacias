<?php

declare(strict_types=1);

namespace App\Enums;

enum TableShapeEnum: string
{
    case SQUARE = 'square';
    case ROUND = 'round';
    case RECTANGLE = 'rectangle';
    case BAR = 'bar';

    public function label(): string
    {
        return match ($this) {
            self::SQUARE => 'Cuadrada',
            self::ROUND => 'Redonda',
            self::RECTANGLE => 'Rectangular',
            self::BAR => 'Barra / Taburete',
        };
    }
}
