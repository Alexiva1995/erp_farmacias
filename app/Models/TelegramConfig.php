<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramConfig extends Model
{
    use HasFactory;

    protected $table = 'telegram_configs';

    protected $fillable = [
        'bot_token',
        'chat_id',
        'admin_chat_id',
        'webhook_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function channels(): HasMany
    {
        return $this->hasMany(TelegramChannel::class, 'telegram_config_id');
    }
}
