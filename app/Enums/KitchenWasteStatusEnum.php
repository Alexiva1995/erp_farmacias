<?php

namespace App\Enums;

enum KitchenWasteStatusEnum: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    /**
     * Etiqueta legible en español del estado de la merma.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente de Aprobación',
            self::APPROVED => 'Aprobada (Pérdida)',
            self::REJECTED => 'Rechazada',
        };
    }

    /**
     * Color de estado para el frontend.
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'error',
        };
    }
}
