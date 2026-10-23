<?php

declare(strict_types=1);

namespace App\Enums;

enum ModifierTypeEnum: string
{
    case ADD = 'add';
    case REMOVE = 'remove';
    case INFO = 'info';

    public function label(): string
    {
        return match ($this) {
            self::ADD => 'Extra / Adición',
            self::REMOVE => 'Sin Ingrediente / Omitir',
            self::INFO => 'Instrucción / Término',
        };
    }
}
