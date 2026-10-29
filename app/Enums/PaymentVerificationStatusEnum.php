<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentVerificationStatusEnum: string
{
    case PENDING_VERIFICATION = 'pending_verification';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_VERIFICATION => 'Pendiente por Conciliar',
            self::APPROVED => 'Aprobado y Conciliado',
            self::REJECTED => 'Rechazado',
        };
    }
}
