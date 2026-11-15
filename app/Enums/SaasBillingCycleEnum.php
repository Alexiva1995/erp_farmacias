<?php

declare(strict_types=1);

namespace App\Enums;

enum SaasBillingCycleEnum: string
{
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case ANNUAL = 'annual';
    case LIFETIME = 'lifetime';

    /**
     * Obtener etiqueta legible en español.
     */
    public function label(): string
    {
        return match ($this) {
            self::MONTHLY => 'Mensual',
            self::QUARTERLY => 'Trimestral',
            self::ANNUAL => 'Anual',
            self::LIFETIME => 'Vitalicia (Pago Único)',
        };
    }

    /**
     * Meses equivalentes para cálculo de renovación y MRR.
     */
    public function monthsCount(): int
    {
        return match ($this) {
            self::MONTHLY => 1,
            self::QUARTERLY => 3,
            self::ANNUAL => 12,
            self::LIFETIME => 120,
        };
    }

    /**
     * Listado de todos los valores del enum.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
