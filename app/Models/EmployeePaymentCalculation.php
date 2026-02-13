<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePaymentCalculation extends Model
{
    protected $fillable = [
        'employee_id',
        'year',
        'month',
        'total_package_usd',
        'salario_base',
        'bono_alimentacion',
        'consumo_farmacia_actual',
        'saldo_deuda_anterior',
        'disponible_para_incentivo',
        'consumo_total_a_descontar',
        'incentivo_metas',
        'nuevo_saldo_deuda',
        'exchange_rate_ves',
        'total_pagado_usd',
        'total_pagado_ves',
    ];

    protected function casts(): array
    {
        return [
            'total_package_usd' => 'decimal:2',
            'salario_base' => 'decimal:2',
            'bono_alimentacion' => 'decimal:2',
            'consumo_farmacia_actual' => 'decimal:2',
            'saldo_deuda_anterior' => 'decimal:2',
            'disponible_para_incentivo' => 'decimal:2',
            'consumo_total_a_descontar' => 'decimal:2',
            'incentivo_metas' => 'decimal:2',
            'nuevo_saldo_deuda' => 'decimal:2',
            'exchange_rate_ves' => 'decimal:4',
            'total_pagado_usd' => 'decimal:2',
            'total_pagado_ves' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getConsumoExcedioPackageAttribute(): bool
    {
        return (float) $this->incentivo_metas === 0.0 && (float) $this->consumo_total_a_descontar > 0;
    }
}
