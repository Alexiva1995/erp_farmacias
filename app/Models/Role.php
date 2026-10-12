<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'id',
        'name',
        'menu_permissions',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'menu_permissions' => 'array',
        ];
    }

    /**
     * Usuarios asociados al rol.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Relación heredada para retrocompatibilidad.
     */
    public function user()
    {
        return $this->hasOne(User::class);
    }
}
