<?php

declare(strict_types=1);

namespace App\Enums;

enum ReturnFiscalStatusEnum: string
{
    case NOT_APPLICABLE = 'not_applicable';
    case PENDING = 'pending';
    case EMITTED = 'emitted';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::NOT_APPLICABLE => 'No aplica',
            self::PENDING => 'NC Pendiente',
            self::EMITTED => 'NC Emitida',
            self::FAILED => 'NC Fallida',
        };
    }
}
