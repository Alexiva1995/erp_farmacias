<?php

declare(strict_types=1);

namespace App\Enums;

enum CategoryTypeEnum: string
{
    case INVENTORY = 'inventory';
    case DISH = 'dish';
    case BOTH = 'both';

    /**
     * Obtener la etiqueta legible en español del tipo de categoría.
     */
    public function label(): string
    {
        return match ($this) {
            self::INVENTORY => 'Inventario (Insumos / Productos)',
            self::DISH => 'Menú (Platos / Recetas)',
            self::BOTH => 'Ambos (Inventario y Menú)',
        };
    }

    /**
     * Obtener listado de todos los valores válidos.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Valores válidos para categorías de inventario/productos.
     *
     * @return array<int, string>
     */
    public static function inventoryValues(): array
    {
        return [
            self::INVENTORY->value,
            self::BOTH->value,
        ];
    }

    /**
     * Valores válidos para categorías de platos/menú.
     *
     * @return array<int, string>
     */
    public static function dishValues(): array
    {
        return [
            self::DISH->value,
            self::BOTH->value,
        ];
    }
}
