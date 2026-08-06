<?php

declare(strict_types=1);

namespace App\Http\Requests\Telegram;

use Illuminate\Foundation\Http\FormRequest;

class ToggleTelegramCommandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}
