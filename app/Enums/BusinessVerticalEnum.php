<?php

declare(strict_types=1);

namespace App\Enums;

enum BusinessVerticalEnum: string
{
    case PHARMACY = 'pharmacy';
    case RESTAURANT = 'restaurant';
    case FAST_FOOD = 'fast_food';
    case MINIMARKET = 'minimarket';
    case DROGUERIA = 'drogueria';
    case SAAS_ADMIN = 'saas_admin';

    /**
     * Obtener el nombre legible de la vertical.
     */
    public function label(): string
    {
        return match ($this) {
            self::PHARMACY => 'Farmacia & Salud Integral',
            self::RESTAURANT => 'Restaurante con Salón (Mesas & Comandas)',
            self::FAST_FOOD => 'Comida Rápida, Cafetería o Dark Kitchen',
            self::MINIMARKET => 'Bodegón, Minimarket & Retail',
            self::DROGUERIA => 'Droguería & Distribución Mayorista B2B',
            self::SAAS_ADMIN => 'Administrador de Suscripciones SaaS & Licencias',
        };
    }

    /**
     * Obtener la descripción operativa del preset.
     */
    public function description(): string
    {
        return match ($this) {
            self::PHARMACY => 'Control de medicamentos, lotes FEFO, fechas de vencimiento, psicotrópicos, laboratorios y récipes médicos.',
            self::RESTAURANT => 'Gestión de mesas, comanderas/KDS por estación, escandallos, recetas, modificadores y división de cuentas.',
            self::FAST_FOOD => 'Venta rápida por mostrador/delivery, comanda directa a cocina, combos, modificadores y cobro express.',
            self::MINIMARKET => 'Venta minorista de alta rotación, lectores de código de barras, bultos y promociones por volumen.',
            self::DROGUERIA => 'Ventas mayoristas a farmacias, días de crédito x producto, cupos/topes, cartera de asesores/telemarketing, WMS picking y marketplace B2B.',
            self::SAAS_ADMIN => 'Consola comercial de software: venta de licencias, catálogo de planes, control de vencimientos/cortes, cobros recurrentes y métricas MRR/ARR.',
        };
    }

    /**
     * Listado de todos los valores válidos.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Intentar resolver desde string tolerando mayúsculas/minúsculas.
     */
    public static function tryFromValue(?string $value): ?self
    {
        if (empty($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));
        return self::tryFrom($normalized);
    }
}
