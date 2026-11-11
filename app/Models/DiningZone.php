<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiningZone extends Model
{
    use HasFactory;

    protected $table = 'dining_zones';

    protected $fillable = [
        'name',
        'background_style',
        'width_meters',
        'height_meters',
        'layout_config',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'width_meters' => 'decimal:2',
        'height_meters' => 'decimal:2',
        'layout_config' => 'array',
    ];

    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class, 'dining_zone_id')->orderBy('id');
    }
}
