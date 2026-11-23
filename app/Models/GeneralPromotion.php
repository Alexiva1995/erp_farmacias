<?php

namespace App\Models;

use App\Models\Concerns\HasPromotionSchedule;
use Illuminate\Database\Eloquent\Model;

class GeneralPromotion extends Model
{
    use HasPromotionSchedule;

    // Campos que se pueden asignar masivamente
    protected $fillable = [
        'name',
        'type',
        'fixed_price',
        'discount_percentage',
        'is_active',
        'categories',
        'start_date',
        'end_date',
        'days_of_week',
        'start_time',
        'end_time',
        'min_order_amount',
        'coupon_code',
        'sales_channel',
        'stackable',
        'priority',
    ];

    // Casteo de tipos de datos
    protected $casts = [
        'is_active' => 'boolean',
        'categories' => 'array',
        'fixed_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'days_of_week' => 'array',
        'min_order_amount' => 'decimal:2',
        'stackable' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Retorna el porcentaje efectivo de descuento para promociones de tipo general.
     */
    public function getEffectiveDiscountPercentage(): float
    {
        if ($this->discount_percentage !== null) {
            return (float) $this->discount_percentage;
        }

        // Compatibilidad con registros antiguos donde se guardaba en fixed_price
        return $this->type === 'general' ? (float) ($this->fixed_price ?? 0) : 0.0;
    }
}
