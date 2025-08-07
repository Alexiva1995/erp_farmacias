<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use HasFactory;

    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'quotations';

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'currency',   // Moneda de la cotización
        'tax_exempt', // Indica si la cotización está exenta de impuestos
        'vat',        // Valor del IVA aplicado
        'total',      // Monto total de la cotización
        'created_by', // ID del usuario que creó la cotización
    ];

    /**
     * Define la relación: Una cotización pertenece a un creador (usuario).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Define la relación: Una cotización tiene muchos productos de cotización.
     */
    public function products(): HasMany
    {
        return $this->hasMany(QuotationProduct::class);
    }
}