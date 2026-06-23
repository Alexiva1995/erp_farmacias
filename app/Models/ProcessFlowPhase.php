<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessFlowPhase extends Model
{
    use HasFactory;

    protected $table = 'process_flow_phases';

    protected $fillable = [
        'flow_id',
        'name',
        'description',
        'sort_order'
    ];

    /**
     * Relación con el flujo padre.
     */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(ProcessFlow::class, 'flow_id');
    }
}
