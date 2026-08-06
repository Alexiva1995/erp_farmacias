<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramChannel extends Model
{
    use HasFactory;

    protected $table = 'telegram_channels';

    protected $fillable = [
        'telegram_config_id',
        'name',
        'chat_id',
        'module',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(TelegramConfig::class, 'telegram_config_id');
    }

    public function commands(): HasMany
    {
        return $this->hasMany(TelegramCommand::class, 'channel_id');
    }
}
