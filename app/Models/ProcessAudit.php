<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessAudit extends Model
{
    use HasFactory;

    protected $table = 'process_audits';

    protected $fillable = [
        'flow_id',
        'order_id',
        'cashier_id',
        'cook_id',
        'total_seconds'
    ];

    /**
     * Relación con el Flujo Utilizado.
     */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(ProcessFlow::class, 'flow_id');
    }

    /**
     * Relación con la Orden.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Relación con el Cajero (Empleado).
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'cashier_id');
    }

    /**
     * Relación con el Cocinero (Empleado).
     */
    public function cook(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'cook_id');
    }

    /**
     * Relación con los tiempos medidos en cada fase.
     */
    public function phases(): HasMany
    {
        return $this->hasMany(ProcessAuditPhase::class, 'process_audit_id');
    }
}
