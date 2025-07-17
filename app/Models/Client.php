<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // De la rama 5.0-CRM
use Illuminate\Database\Eloquent\Factories\HasFactory; // De la rama develop

class Client extends Model
{
    // Constantes para los tipos de identificación de la rama 5.0-CRM
    const IDENTIFICATION_TYPE_VENEZOLANO = "V-";
    const IDENTIFICATION_TYPE_GOBIERNO = "G-";
    const IDENTIFICATION_TYPE_EXTRANJERO = "E-";
    const IDENTIFICATION_TYPE_JURIDICO = "J-";

    use SoftDeletes; // De la rama 5.0-CRM
    use HasFactory; // De la rama develop

    protected $fillable = [
        'identification',
        'identification_type',
        'name',
        'last_name',
        'email',
        'phone',
        'address',
        'birthdate', // De la rama 5.0-CRM
        'company_id' // De la rama 5.0-CRM
    ];

    // Casts para los campos de fecha de la rama 5.0-CRM
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con el modelo Company (de la rama 5.0-CRM)
     * Un cliente pertenece a una compañía.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Accesorio para el nombre completo (de la rama 5.0-CRM)
     * Combina el nombre y el apellido en un solo atributo.
     */
    public function getFullNameAttribute()
    {
        return "{$this->name} {$this->last_name}";
    }

    /**
     * Relación con el modelo Order (de la rama develop)
     * Un cliente puede tener muchas órdenes.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Relación con el modelo Credit (de la rama develop)
     * Un cliente puede tener muchos créditos.
     */
    public function credits()
    {
        return $this->hasMany(Credit::class);
    }

    public function pendingCredits()
    {
        return $this->hasMany(Credit::class)->where('status', 'Active');
    }
}
