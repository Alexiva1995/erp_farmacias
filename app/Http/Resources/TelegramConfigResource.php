<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TelegramConfigResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Enmascarar bot_token: solo mostramos los últimos 4 chars para confirmar configuración sin exponer el secreto.
        $maskedToken = $this->bot_token
            ? str_repeat('*', max(0, strlen($this->bot_token) - 4)) . substr($this->bot_token, -4)
            : null;

        return [
            'id'           => $this->id,
            'bot_token'    => $maskedToken,
            'chat_id'      => $this->chat_id,
            'admin_chat_id'=> $this->admin_chat_id,
            'webhook_url'  => $this->webhook_url,
            'is_active'    => (bool) $this->is_active,
            'created_at'   => $this->created_at?->toDateTimeString(),
            'updated_at'   => $this->updated_at?->toDateTimeString(),
        ];
    }
}
