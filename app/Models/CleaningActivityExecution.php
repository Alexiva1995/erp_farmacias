<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CleaningActivityExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'cleaning_activity_id',
        'approved_by',
        'scheduled_date',
        'due_date',
        'completed_date',
        'approved_date',
        'status',
        'photo',
        'notes',
        'rejection_reason',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'due_date' => 'date',
        'completed_date' => 'datetime',
        'approved_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * =================================================================================================
     * RELACIONES
     * =================================================================================================
     */

    /**
     * Empleado asignado a esta ejecución
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Actividad de limpieza a realizar
     */
    public function cleaningActivity(): BelongsTo
    {
        return $this->belongsTo(CleaningActivity::class);
    }

    /**
     * Usuario supervisor que aprobó la actividad
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * =================================================================================================
     * SCOPES
     * =================================================================================================
     */

    /**
     * Scope para filtrar por estado
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para obtener actividades pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'Pendiente');
    }

    /**
     * Scope para obtener actividades procesadas (esperando aprobación)
     */
    public function scopeProcessed($query)
    {
        return $query->where('status', 'Procesada');
    }

    /**
     * Scope para obtener actividades completadas
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'Completada');
    }

    /**
     * Scope para obtener actividades vencidas
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'Vencida');
    }

    /**
     * Scope para filtrar por empleado
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope para filtrar por rango de fechas (scheduled_date)
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('scheduled_date', [$startDate, $endDate]);
    }

    /**
     * Scope para filtrar por rango de fechas límite (due_date)
     */
    public function scopeBetweenDueDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('due_date', [$startDate, $endDate]);
    }

    /**
     * Scope para obtener actividades del día de hoy (scheduled_date)
     */
    public function scopeToday($query)
    {
        return $query->whereDate('scheduled_date', today());
    }

    /**
     * Scope para obtener actividades que vencen hoy
     */
    public function scopeDueToday($query)
    {
        return $query->whereDate('due_date', today());
    }

    /**
     * Scope para obtener actividades atrasadas que aún están pendientes
     */
    public function scopeLate($query)
    {
        return $query->where('status', 'Pendiente')
            ->where('due_date', '<', today());
    }

    /**
     * Scope para obtener actividades próximas a vencer (X días)
     */
    public function scopeDueSoon($query, $days = 2)
    {
        return $query->where('status', 'Pendiente')
            ->whereBetween('due_date', [today(), today()->addDays($days)]);
    }

    /**
     * =================================================================================================
     * MÉTODOS DE AYUDA
     * =================================================================================================
     */

    /**
     * Verifica si la ejecución está pendiente
     */
    public function isPending(): bool
    {
        return $this->status === 'Pendiente';
    }

    /**
     * Verifica si la ejecución está procesada (esperando aprobación)
     */
    public function isProcessed(): bool
    {
        return $this->status === 'Procesada';
    }

    /**
     * Verifica si la ejecución está completada
     */
    public function isCompleted(): bool
    {
        return $this->status === 'Completada';
    }

    /**
     * Verifica si la ejecución está vencida
     */
    public function isOverdue(): bool
    {
        return $this->status === 'Vencida';
    }

    /**
     * Verifica si la ejecución está atrasada (pendiente y pasó la fecha límite)
     */
    public function isLate(): bool
    {
        return $this->isPending() && $this->due_date->isPast();
    }

    /**
     * Verifica si la ejecución vence pronto (en X días)
     */
    public function isDueSoon(int $days = 2): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        return $this->due_date->between(today(), today()->addDays($days));
    }

    /**
     * Obtiene los días restantes hasta la fecha límite
     */
    public function daysUntilDue(): int
    {
        return today()->diffInDays($this->due_date, false);
    }

    /**
     * Verifica si la ejecución puede ser marcada como procesada
     */
    public function canBeProcessed(): bool
    {
        return $this->isPending() && $this->photo !== null;
    }

    /**
     * Verifica si la ejecución puede ser aprobada
     */
    public function canBeApproved(): bool
    {
        return $this->isProcessed();
    }

    /**
     * Marca la ejecución como procesada (empleado completó y subió foto)
     */
    public function markAsProcessed(): bool
    {
        if (!$this->canBeProcessed()) {
            return false;
        }

        $this->status = 'Procesada';
        $this->completed_date = now();
        return $this->save();
    }

    /**
     * Aprueba la ejecución (supervisor)
     */
    public function approve(int $supervisorId): bool
    {
        if (!$this->canBeApproved()) {
            return false;
        }

        $this->status = 'Completada';
        $this->approved_by = $supervisorId;
        $this->approved_date = now();
        return $this->save();
    }

    /**
     * Rechaza la ejecución y la devuelve a pendiente
     */
    public function reject(int $supervisorId, string $reason): bool
    {
        if (!$this->isProcessed()) {
            return false;
        }

        $this->status = 'Pendiente';
        $this->rejection_reason = $reason;
        $this->completed_date = null;
        $this->photo = null;
        return $this->save();
    }

    /**
     * Marca la ejecución como vencida
     */
    public function markAsOverdue(): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->status = 'Vencida';
        return $this->save();
    }
}
