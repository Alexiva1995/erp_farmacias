<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationProduct extends Model
{
    use HasFactory; // Se mantiene el trait HasFactory de la rama 4.0-TPV

    /**
     * La tabla asociada con el modelo.
     * Se define explícitamente el nombre de la tabla para mayor claridad.
     *
     * @var string
     */
    protected $table = 'quotation_products';

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'quotation_id', // ID de la cotización a la que pertenece este producto
        'product_id',   // ID del producto asociado
        'dish_id',      // ID del platillo asociado
        'units',        // Cantidad de unidades del producto en la cotización
    ];

    /**
     * Define la relación: Un producto de cotización pertenece a una cotización.
     * Se añade el tipo de retorno BelongsTo de la rama develop.
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * Define la relación: Un producto de cotización pertenece a un producto.
     * Se añade el tipo de retorno BelongsTo de la rama develop.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Define la relación: Un producto de cotización pertenece a un platillo.
     */
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }
}
