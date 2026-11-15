<?php

declare(strict_types=1);

namespace App\Enums;

enum SaasSubscriptionStatusEnum: string
{
    case TRIAL = 'trial';
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case SUSPENDED = 'suspended';
    case CANCELLED = 'cancelled';

    /**
     * Obtener etiqueta legible en español.
     */
    public function label(): string
    {
        return match ($this) {
            self::TRIAL => 'Periodo de Prueba',
            self::ACTIVE => 'Activa',
            self::PAST_DUE => 'Pago Pendiente (Gracia)',
            self::SUSPENDED => 'Suspendida por Falta de Pago',
            self::CANCELLED => 'Cancelada',
        };
    }

    /**
     * Obtener color semántico de Vuetify.
     */
    public function color(): string
    {
        return match ($this) {
            self::TRIAL => 'info',
            self::ACTIVE => 'success',
            self::PAST_DUE => 'warning',
            self::SUSPENDED => 'error',
            self::CANCELLED => 'secondary',
        };
    }

    /**
     * Determina si la licencia permite operar en el sistema.
     */
    public function canOperate(): bool
    {
        return in_array($this, [self::TRIAL, self::ACTIVE, self::PAST_DUE], true);
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
