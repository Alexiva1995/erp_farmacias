<?php

declare(strict_types=1);

namespace App\Http\Requests\Telegram;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTelegramChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'chat_id' => ['required', 'string', 'max:255'],
            'module' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
