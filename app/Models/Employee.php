<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        "name",
        "last_name",
        "identification",
        "is_active",
        "photo",
        "rif",
        "residence_letter",
        "cv",
        "user_id",
        "total_package_usd",
        "saldo_deuda",
    ];

    /**
     * =================================================================================================
     * RELACIONES
     * =================================================================================================
     */

    /**
     * Un empleado pertenece a un usuario.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Un empleado puede tener asignados muchos laboratorios.
     * Un laboratorio puede estar asignado a muchos empleados.
     */
    public function laboratories(): BelongsToMany
    {
        return $this->belongsToMany(Laboratory::class, 'employee_laboratory')
            ->withTimestamps();
    }

    /**
     * Un empleado puede tener asignados muchos productos.
     * Un producto puede estar asignado a muchos empleados.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'employee_product')
            ->withTimestamps();
    }

    /**
     * Un empleado puede tener asignadas muchas actividades de limpieza.
     * Una actividad de limpieza puede estar asignada a muchos empleados.
     */
    public function cleaningActivities(): BelongsToMany
    {
        return $this->belongsToMany(CleaningActivity::class, 'employee_cleaning_activity')
            ->withPivot(['status', 'assigned_date', 'completed_date', 'notes'])
            ->withTimestamps();
    }

    public function settlement()
    {
        return $this->hasOne(EmployeeSettlement::class);
    }

    public function resignation()
    {
        return $this->hasOne(Resignation::class);
    }
    public function cleaningActivityExecutions()
    {
        return $this->hasMany(CleaningActivityExecution::class);
    }

    /**
     * Ejecuciones pendientes
     */
    public function pendingExecutions()
    {
        return $this->hasMany(CleaningActivityExecution::class)
            ->where('status', 'Pendiente');
    }

    /**
     * Ejecuciones procesadas (esperando aprobación)
     */
    public function processedExecutions()
    {
        return $this->hasMany(CleaningActivityExecution::class)
            ->where('status', 'Procesada');
    }

    public function paymentCalculations()
    {
        return $this->hasMany(EmployeePaymentCalculation::class)->orderByDesc('year')->orderByDesc('month');
    }
}
