<?php

declare(strict_types=1);

namespace App\Enums;

enum ModifierTypeEnum: string
{
    case ADD        = 'add';
    case REMOVE     = 'remove';
    case INFO       = 'info';
    // Reemplaza un insumo de la receta por otro conservando la porción original
    case SUBSTITUTE = 'substitute';

    public function label(): string
    {
        return match ($this) {
            self::ADD        => 'Extra / Adición',
            self::REMOVE     => 'Sin Ingrediente / Omitir',
            self::INFO       => 'Instrucción / Término',
            self::SUBSTITUTE => 'Sustitución de Insumo (misma porción de la receta)',
        };
    }
}
