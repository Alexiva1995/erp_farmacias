<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Resignation extends Model
{
    protected $fillable = [
        'employee_id', 
        'employee_name', 
        'employee_identification',
        'employee_email', 
        'employee_position', 
        'start_date',
        'resignation_type', 
        'request_date', 
        'effective_date',
        'employee_status'
    ];
    
    protected $casts = [
        'start_date' => 'date',
        'request_date' => 'date', 
        'effective_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    /**
     * Relación con Employee
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
    
    /**
     * Relación con User (via Employee)
     */
    public function user(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class, 
            Employee::class, 
            'id', // Foreign key en employees
            'id', // Foreign key en users
            'employee_id', // Local key en resignations
            'user_id' // Local key en employees
        );
    }
    
    /**
     * Scope para filtrar por tipo de renuncia
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('resignation_type', $type);
    }
    
    /**
     * Scope para filtrar por estado del empleado
     */
    public function scopeByEmployeeStatus($query, string $status)
    {
        return $query->where('employee_status', $status);
    }
    
    /**
     * Scope para buscar por nombre, identificación o email
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('employee_name', 'like', "%{$search}%")
              ->orWhere('employee_identification', 'like', "%{$search}%")
              ->orWhere('employee_email', 'like', "%{$search}%");
        });
    }
}
