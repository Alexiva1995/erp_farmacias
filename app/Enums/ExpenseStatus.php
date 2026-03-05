<?php

namespace App\Enums;

enum ExpenseStatus: string
{
    case PENDING = 'Pending';
    case APPROVED = 'Approved';
    case CANCELLED = 'Cancelled';

    /**
     * Obtener todas las etiquetas en español para los estados.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::APPROVED => 'Aprobado',
            self::CANCELLED => 'Cancelado',
        };
    }
}
