<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenStation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'color',
        'icon',
        'printer_ip',
        'is_active',
        'sort_order',
        'pos_x',
        'pos_y',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'pos_x' => 'float',
        'pos_y' => 'float',
    ];

    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class, 'kitchen_station_id');
    }
}
