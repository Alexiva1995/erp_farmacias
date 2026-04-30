<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupsLaboratory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Relación con los laboratorios que pertenecen a este grupo.
     */
    public function laboratories(): HasMany
    {
        return $this->hasMany(Laboratory::class, 'group_id');
    }
}
