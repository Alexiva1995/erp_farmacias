<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'products';

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'active_ingredient',
        'laboratory_id',
        'supplier_id',
        'origin_id',
        'category_id',
        'group_id',
        'cost_price',
        'sale_price',
        'iva',
        'from_colombia',
        'psychotropic',
        'barcode',
        'photo_url',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */


    /**
     * =================================================================================================
     * RELACIONES
     * =================================================================================================
     */

    /**
     * Un producto pertenece a un laboratorio.
     */
    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }

    /**
     * Un producto pertenece a un proveedor.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Un producto tiene un origen.
     */
    public function origin(): BelongsTo
    {
        return $this->belongsTo(Origin::class);
    }

    /**
     * Un producto pertenece a una categoría.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Un producto pertenece a un grupo de productos.
     */
    public function group(): BelongsTo
    {
        // Asegúrate de que el modelo para 'groups_products' se llame 'ProductGroup' o ajústalo.
        return $this->belongsTo(GroupsProduct::class, 'group_id');
    }

    /**
     * Un producto tiene muchos lotes.
     */
    public function lots(): HasMany
    {
        return $this->hasMany(ProductLot::class);
    }
}
