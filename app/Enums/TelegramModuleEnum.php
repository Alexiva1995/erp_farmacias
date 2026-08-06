<?php

declare(strict_types=1);

namespace App\Enums;

enum TelegramModuleEnum: string
{
    case GENERALES = 'generales';
    case FARMACIA = 'farmacia';
    case RESTAURANTE = 'restaurante';
    case COSMETICOS = 'cosmeticos';
    case ALQUILERES = 'alquileres';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::GENERALES => 'Generales',
            self::FARMACIA => 'Farmacia',
            self::RESTAURANTE => 'Restaurante',
            self::COSMETICOS => 'Cosméticos',
            self::ALQUILERES => 'Alquileres',
            self::SYSTEM => 'Sistema / General',
        };
    }
}
