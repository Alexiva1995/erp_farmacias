<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Laboratory extends Model
{
    use HasFactory;

    /**
     * La tabla asociada con el modelo.
     * Aunque Laravel lo infiere, es buena práctica especificarlo.
     *
     * @var string
     */
    protected $table = 'laboratories';

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'group_id',
    ];

    /**
     * =================================================================================================
     * RELACIONES
     * =================================================================================================
     */

    public function group(): BelongsTo
    {
        return $this->belongsTo(GroupsLaboratory::class, 'group_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'suppliers_laboratories');
    }
}
