<?php

namespace App\Enums;

enum FixedExpenseOccurrenceStatus: string
{
    case PENDING = 'Pending';
    case PAID = 'Paid';
    case SKIPPED = 'Skipped';

    /**
     * Etiqueta en español del estado de la cuota.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Por pagar',
            self::PAID => 'Pago registrado',
            self::SKIPPED => 'Omitida',
        };
    }
}
