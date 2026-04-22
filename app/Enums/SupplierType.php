<?php

namespace App\Enums;

enum SupplierType: string
{
    case DROGUERIA = 'drogueria';
    case EXTERNO = 'externo';

    /**
     * Obtiene la etiqueta amigable del tipo
     */
    public function label(): string
    {
        return match ($this) {
            self::DROGUERIA => 'Droguería',
            self::EXTERNO   => 'Proveedor Externo',
        };
    }
}
