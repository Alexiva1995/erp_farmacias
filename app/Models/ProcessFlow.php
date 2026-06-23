<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessFlow extends Model
{
    use HasFactory;

    protected $table = 'process_flows';

    protected $fillable = [
        'name',
        'description',
        'is_active'
    ];

    /**
     * Relación con las fases del flujo.
     */
    public function phases(): HasMany
    {
        return $this->hasMany(ProcessFlowPhase::class, 'flow_id')->orderBy('sort_order');
    }

    /**
     * Relación con las auditorías asociadas.
     */
    public function audits(): HasMany
    {
        return $this->hasMany(ProcessAudit::class, 'flow_id');
    }
}
