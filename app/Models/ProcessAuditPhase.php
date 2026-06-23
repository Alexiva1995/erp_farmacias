<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessAuditPhase extends Model
{
    use HasFactory;

    protected $table = 'process_audit_phases';

    protected $fillable = [
        'process_audit_id',
        'flow_phase_id',
        'seconds'
    ];

    /**
     * Relación con la auditoría padre.
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(ProcessAudit::class, 'process_audit_id');
    }

    /**
     * Relación con la definición de la fase.
     */
    public function flowPhase(): BelongsTo
    {
        return $this->belongsTo(ProcessFlowPhase::class, 'flow_phase_id');
    }
}
