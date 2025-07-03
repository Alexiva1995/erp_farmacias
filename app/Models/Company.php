<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // De la rama 5.0-CRM
use Illuminate\Database\Eloquent\Factories\HasFactory; // De la rama develop

class Company extends Model
{
    // Constantes para los tipos de compañía de la rama 5.0-CRM
    const COMPANY = "Empresa";
    const CLINIC = "Clinica";

    use SoftDeletes; // De la rama 5.0-CRM
    use HasFactory; // De la rama develop

    protected $fillable = [
        // "id", // Eliminado: Laravel maneja 'id' automáticamente si es auto-incrementable
        "name",
        "identification",
        "address",
        "type_company", // De la rama 5.0-CRM
    ];

    /**
     * Relación con el modelo Client (de la rama 5.0-CRM)
     * Una compañía puede tener muchos clientes.
     */
    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    /**
     * Relación con el modelo CompanyOffer (de la rama develop)
     * Una compañía puede tener muchas ofertas.
     */
    public function offers()
    {
        return $this->hasMany(CompanyOffer::class);
    }
}