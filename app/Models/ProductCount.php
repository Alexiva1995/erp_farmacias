<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCount extends Model
{
    protected $fillable = [
        'cycle_id',
        'product_id',
        'product_lot_id',
        'counted_quantity',
        'system_quantity',
        'discrepancy',
        'status',
        'user_id',
        'supervisor_id',
    ];

    protected $casts = [
        'counted_quantity' => 'integer',
        'system_quantity' => 'integer',
        'discrepancy' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relaciones existentes
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(InventoryCycle::class, 'cycle_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productLot(): BelongsTo
    {
        return $this->belongsTo(ProductLot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    // Scopes útiles
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // Accessors y métodos útiles
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pendiente',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
            default => 'Desconocido'
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'error',
            default => 'secondary'
        };
    }

    public function isPositiveCount(): bool
    {
        return $this->counted_quantity > 0;
    }

    public function isNegativeCount(): bool
    {
        return $this->counted_quantity < 0;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function hasDiscrepancy(): bool
    {
        return $this->discrepancy != 0;
    }

    public function getDiscrepancyTypeAttribute(): string
    {
        if ($this->discrepancy > 0) {
            return 'exceso';
        } elseif ($this->discrepancy < 0) {
            return 'faltante';
        }
        return 'sin_discrepancia';
    }

    public function getAbsoluteDiscrepancyAttribute(): int
    {
        return abs($this->discrepancy ?? 0);
    }
}
