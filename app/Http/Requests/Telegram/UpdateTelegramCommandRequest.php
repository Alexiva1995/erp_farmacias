<?php

declare(strict_types=1);

namespace App\Http\Requests\Telegram;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTelegramCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'command' => ['required', 'string', 'max:255'],
            'alias' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'channel_id' => ['nullable', 'exists:telegram_channels,id'],
            'is_active' => ['boolean'],
            'payload_template' => ['nullable', 'string'],
        ];
    }
}
