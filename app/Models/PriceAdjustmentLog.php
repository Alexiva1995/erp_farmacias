<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceAdjustmentLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'month',
        'expired_log_id',
        'product_id',
        'product_name',
        'lot_id',
        'lot_number',
        'cost_redistributed',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'cost_redistributed' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    /**
     * Relación con el log de productos caducados
     */
    public function expiredLog(): BelongsTo
    {
        return $this->belongsTo(ExpiredLog::class, 'expired_log_id');
    }

    /**
     * Relación con el producto
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relación con el lote
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class, 'lot_id');
    }

    /**
     * Relación con el usuario que procesó el reajuste
     */
    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scope para filtrar por mes
     */
    public function scopeByMonth($query, $month)
    {
        return $query->where('month', $month);
    }

    /**
     * Scope para filtrar por producto
     */
    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }
}
