<?php

namespace App\Enums;

enum ProductionOrderStatusEnum: string
{
    case DRAFT = 'draft';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::IN_PROGRESS => 'En Proceso',
            self::COMPLETED => 'Completada',
            self::CANCELLED => 'Cancelada',
        };
    }
}
