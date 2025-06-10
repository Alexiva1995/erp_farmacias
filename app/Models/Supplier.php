<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'suppliers';

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'supplier_name',
        'social_reason',
        'sales_phone',
        'collections_phone',
        'credit_days',
        'dispatch_days',
        'order_days',
        'payment_method',
        'cash_payment',
        'charges_igtf',
        'rating',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     * Esto es muy útil para manejar JSON, booleanos, fechas, etc.
     *
     * @var array<string, string>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function laboratories(): BelongsToMany
    {
        return $this->belongsToMany(Laboratory::class, 'suppliers_laboratories');
    }
}
