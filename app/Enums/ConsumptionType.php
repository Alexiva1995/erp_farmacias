<?php

declare(strict_types=1);

namespace App\Enums;

enum ConsumptionType: string
{
    case CHRONIC = "chronic";
    case SINGLE_TREATMENT = "single_treatment";
    case SPORADIC = "sporadic";

    public function label(): string
    {
        return match ($this) {
            self::CHRONIC => "Crónico (Uso Continuo)",
            self::SINGLE_TREATMENT => "Tratamiento Único / Ciclo",
            self::SPORADIC => "Esporádico / Ocasional",
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::CHRONIC => "primary",
            self::SINGLE_TREATMENT => "warning",
            self::SPORADIC => "secondary",
        };
    }

    public function hasReminder(): bool
    {
        return $this !== self::SPORADIC;
    }
}
