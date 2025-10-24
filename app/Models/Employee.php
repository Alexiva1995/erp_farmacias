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
        "user_id"
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
    public function settlement()
    {
        return $this->hasOne(EmployeeSettlement::class);
    }

    public function resignation()
    {
        return $this->hasOne(Resignation::class);
    }
}
