<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryRoute extends Model
{
    use HasFactory;

    protected $table = 'delivery_routes';

    protected $fillable = [
        'code',
        'name',
        'zone_state',
        'driver_name',
        'vehicle_plate',
        'dispatch_days',
        'is_active',
    ];

    protected $casts = [
        'dispatch_days' => 'array',
        'is_active' => 'boolean',
    ];

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'delivery_route_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'delivery_route_id');
    }
}
