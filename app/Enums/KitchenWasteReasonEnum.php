<?php

namespace App\Enums;

enum KitchenWasteReasonEnum: string
{
    case EXPIRED = 'expired';
    case BURNED_SPOILED = 'burned_spoiled';
    case PREPARATION_TRIM = 'preparation_trim';
    case STAFF_MEAL = 'staff_meal';
    case COURTESY = 'courtesy';
    case DROPPED = 'dropped';

    public function label(): string
    {
        return match ($this) {
            self::EXPIRED => 'Vencimiento / Caducado',
            self::BURNED_SPOILED => 'Quemado / Malogrado',
            self::PREPARATION_TRIM => 'Merma de Limpieza / Cocción',
            self::STAFF_MEAL => 'Comida de Personal',
            self::COURTESY => 'Cortesía de la Casa',
            self::DROPPED => 'Caída / Accidente',
        };
    }
}
