<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoReplenishmentConfig extends Model
{
    protected $fillable = [
        'name',
        'is_active',
        'tipo_filtracion',
        'lapso_de_tiempo',
        'min_solicitar',
        'con_descuento',
        'stock_filter',
        'supplier_id',
        'group_ids',
        'schedule_expression',
        'last_run_at',
        'last_run_products',
        'last_run_orders',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'con_descuento' => 'boolean',
        'group_ids'     => 'array',
        'min_solicitar' => 'float',
        'last_run_at'   => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
