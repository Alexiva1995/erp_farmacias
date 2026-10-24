<?php

declare(strict_types=1);

namespace App\Enums;

enum KitchenStatusEnum: string
{
    case DRAFT = 'draft';
    case QUEUED = 'queued';
    case PREPARING = 'preparing';
    case READY = 'ready';
    case SERVED = 'served';
    case VOIDED = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::QUEUED => 'En Cola',
            self::PREPARING => 'Preparando',
            self::READY => 'Listo / Pase',
            self::SERVED => 'Servido',
            self::VOIDED => 'Anulado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::QUEUED => 'info',
            self::PREPARING => 'warning',
            self::READY => 'success',
            self::SERVED => 'primary',
            self::VOIDED => 'error',
        };
    }
}
