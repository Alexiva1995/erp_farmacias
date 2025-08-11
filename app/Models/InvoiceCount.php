<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceCount extends Model
{
    use HasFactory;

    /**
     * El nombre de la tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'invoices_counts';

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'cycle_id',
        'product_id',
        'counted_quantity',
        'system_quantity',
        'discrepancy',
        'type',
        'status',
        'user_id',
        'supervisor_id',
    ];

    /**
     * Define la relación con el ciclo de inventario.
     */
    public function cycle()
    {
        return $this->belongsTo(InventoryCycle::class, 'cycle_id');
    }

    /**
     * Define la relación con el producto.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Define la relación con el usuario que realizó el conteo.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Define la relación con el supervisor que aprobó/rechazó.
     */
    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * Un conteo puede tener muchas distribuciones en diferentes lotes.
     */
    public function distributions()
    {
        return $this->hasMany(InvoiceCountDistribution::class, 'invoice_count_id');
    }

}
