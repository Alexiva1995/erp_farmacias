<?php

namespace App\Enums;

enum FixedExpenseIntervalUnit: string
{
    case DAY = 'day';
    case WEEK = 'week';
    case MONTH = 'month';
    case YEAR = 'year';

    /**
     * Etiqueta en español de la unidad de frecuencia.
     */
    public function label(): string
    {
        return match ($this) {
            self::DAY => 'Día(s)',
            self::WEEK => 'Semana(s)',
            self::MONTH => 'Mes(es)',
            self::YEAR => 'Año(s)',
        };
    }

    /**
     * Descripción legible de la frecuencia (ej. "Cada 3 meses").
     */
    public function describe(int $count): string
    {
        if ($count === 1) {
            return match ($this) {
                self::DAY => 'Diario',
                self::WEEK => 'Semanal',
                self::MONTH => 'Mensual',
                self::YEAR => 'Anual',
            };
        }

        if ($this === self::MONTH) {
            $named = [2 => 'Bimestral', 3 => 'Trimestral', 6 => 'Semestral'];
            if (isset($named[$count])) {
                return $named[$count];
            }
        }

        if ($this === self::WEEK && $count === 2) {
            return 'Quincenal';
        }

        $plural = match ($this) {
            self::DAY => 'días',
            self::WEEK => 'semanas',
            self::MONTH => 'meses',
            self::YEAR => 'años',
        };

        return "Cada {$count} {$plural}";
    }
}
