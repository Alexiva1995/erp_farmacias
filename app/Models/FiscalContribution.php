<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalContribution extends Model
{
    use HasFactory;

    protected $table = 'fiscal_contributions';

    protected $fillable = [
        'period',
        'tax_type',
        'document_number',
        'operation_date',
        'due_date',
        'amount',
        'status',
        'payment_date',
        'payment_reference',
        'source',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'operation_date' => 'date:Y-m-d',
        'due_date'       => 'date:Y-m-d',
        'payment_date'   => 'date:Y-m-d',
        'amount'         => 'float',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope para filtrar por estado.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para pendientes.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope para pagados.
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope para vencidos (pendientes con fecha de vencimiento menor a hoy).
     */
    public function scopeExpired($query)
    {
        return $query->where('status', 'pending')
            ->whereDate('due_date', '<', now()->toDateString());
    }
}
