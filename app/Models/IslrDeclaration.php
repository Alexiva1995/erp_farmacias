<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IslrDeclaration extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'amount',
        'status',
        'declaration_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'declaration_date' => 'date',
        'year' => 'integer',
    ];

    /**
     * Scope para filtrar por año
     */
    public function scopeForYear($query, $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope para declaraciones pagadas
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope para declaraciones no pagadas
     */
    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    /**
     * Scope para última declaración
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('declaration_date', 'desc');
    }

    /**
     * Verificar si la declaración está pagada
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Marcar como pagada
     */
    public function markAsPaid()
    {
        $this->update(['status' => 'paid']);
    }

    /**
     * Marcar como no pagada
     */
    public function markAsUnpaid()
    {
        $this->update(['status' => 'unpaid']);
    }

    /**
     * Obtener el texto del estado
     */
    public function getStatusTextAttribute(): string
    {
        return $this->status === 'paid' ? 'Pagado' : 'No Pagado';
    }

    /**
     * Obtener el color del estado
     */
    public function getStatusColorAttribute(): string
    {
        return $this->status === 'paid' ? 'success' : 'warning';
    }
}
