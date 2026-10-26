<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class A2SyncTask extends Model
{
    protected $table = 'a2_sync_tasks';

    protected $fillable = [
        'section',
        'year',
        'status',
        'extracted_data',
        'preview_data',
        'error_message',
        'created_by',
    ];

    protected $casts = [
        'year'           => 'integer',
        'extracted_data' => 'array',
        'preview_data'   => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
