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

    /**
     * Indica si el motivo corresponde a una merma operativa real (desperdicio de cocina)
     * frente a un consumo interno o comercial (comida de personal / cortesía).
     */
    public function isOperationalWaste(): bool
    {
        return match ($this) {
            self::EXPIRED,
            self::BURNED_SPOILED,
            self::PREPARATION_TRIM,
            self::DROPPED => true,
            self::STAFF_MEAL,
            self::COURTESY => false,
        };
    }

    /**
     * Indica si el motivo exige evidencia fotográfica obligatoria según el estándar gastronómico.
     */
    public function requiresPhoto(): bool
    {
        return match ($this) {
            self::EXPIRED,
            self::BURNED_SPOILED,
            self::DROPPED => true,
            self::PREPARATION_TRIM,
            self::STAFF_MEAL,
            self::COURTESY => false,
        };
    }

    /**
     * Categoría contable del motivo de merma.
     */
    public function categoryLabel(): string
    {
        return $this->isOperationalWaste()
            ? 'Merma Operativa (Desperdicio)'
            : 'Consumo Interno / Comercial';
    }

    /**
     * Lista de valores que representan desperdicio operativo real.
     */
    public static function operationalValues(): array
    {
        return [
            self::EXPIRED->value,
            self::BURNED_SPOILED->value,
            self::PREPARATION_TRIM->value,
            self::DROPPED->value,
        ];
    }

    /**
     * Lista de valores que representan consumo interno o cortesías.
     */
    public static function internalConsumptionValues(): array
    {
        return [
            self::STAFF_MEAL->value,
            self::COURTESY->value,
        ];
    }
}
