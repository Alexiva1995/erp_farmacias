<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CleaningActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'activity',
        'description',
        'frequency',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * =================================================================================================
     * RELACIONES
     * =================================================================================================
     */

    /**
     * Una actividad de limpieza puede estar asignada a muchos empleados.
     * Un empleado puede tener muchas actividades de limpieza.
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_cleaning_activity')
            ->withPivot(['status', 'assigned_date', 'completed_date', 'notes'])
            ->withTimestamps();
    }

    /**
     * =================================================================================================
     * SCOPES
     * =================================================================================================
     */

    /**
     * Scope para filtrar por frecuencia
     */
    public function scopeByFrequency($query, $frequency)
    {
        return $query->where('frequency', $frequency);
    }

    /**
     * Scope para búsqueda
     */
    public function scopeSearch($query, $search, $isStrict = false)
    {
        return $query->where(function ($q) use ($search, $isStrict) {
            $operator = $isStrict ? '=' : 'like';
            $value = $isStrict ? $search : "%{$search}%";
            
            $q->where('activity', $operator, $value)
                ->orWhere('description', $operator, $value);
        });
    }

    /**
     * Obtener actividades ordenadas
     */
    public function scopeOrdered($query, $sortBy = 'activity', $order = 'asc')
    {
        return $query->orderBy($sortBy, $order);
    }

    public function executions()
    {
        return $this->hasMany(CleaningActivityExecution::class);
    }

    /**
     * Ejecuciones pendientes de esta actividad
     */
    public function pendingExecutions()
    {
        return $this->hasMany(CleaningActivityExecution::class)
            ->where('status', 'Pendiente');
    }
}
