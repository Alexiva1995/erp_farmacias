<?php

declare(strict_types=1);

namespace App\Enums;

enum CourseNumberEnum: int
{
    case STARTER = 1;
    case MAIN = 2;
    case DESSERT = 3;

    public function label(): string
    {
        return match ($this) {
            self::STARTER => '1° Tiempo (Entrada)',
            self::MAIN => '2° Tiempo (Fuerte)',
            self::DESSERT => '3° Tiempo (Postre)',
        };
    }
}
