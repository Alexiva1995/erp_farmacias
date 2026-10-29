<?php

declare(strict_types=1);

namespace App\Enums;

enum B2BOrderStatusEnum: string
{
    case DRAFT = 'draft';
    case PENDING_CREDIT = 'pending_credit';
    case APPROVED_CREDIT = 'approved_credit';
    case PICKING = 'picking';
    case CHECKING = 'checking';
    case PACKED = 'packed';
    case DISPATCHED = 'dispatched';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::PENDING_CREDIT => 'Por Aprobar Crédito',
            self::APPROVED_CREDIT => 'Crédito Aprobado',
            self::PICKING => 'En Picking (Almacén)',
            self::CHECKING => 'En Doble Chequeo',
            self::PACKED => 'Embalado / Listo',
            self::DISPATCHED => 'En Ruta de Despacho',
            self::DELIVERED => 'Entregado a Farmacia',
            self::CANCELLED => 'Cancelado',
        };
    }
}
