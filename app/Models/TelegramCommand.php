<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TelegramModuleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramCommand extends Model
{
    use HasFactory;

    protected $table = 'telegram_commands';

    protected $fillable = [
        'module',
        'channel_id',
        'command',
        'alias',
        'description',
        'is_active',
        'payload_template',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'module' => TelegramModuleEnum::class,
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(TelegramChannel::class, 'channel_id');
    }
}
