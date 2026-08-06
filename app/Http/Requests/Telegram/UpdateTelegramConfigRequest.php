<?php

declare(strict_types=1);

namespace App\Http\Requests\Telegram;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTelegramConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bot_token' => ['nullable', 'string', 'max:255'],
            'chat_id' => ['nullable', 'string', 'max:255'],
            'admin_chat_id' => ['nullable', 'string', 'max:255'],
            'webhook_url' => ['nullable', 'url', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }
}
