<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TelegramCommandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'module' => is_object($this->module) ? $this->module->value : $this->module,
            'module_label' => is_object($this->module) && method_exists($this->module, 'label') ? $this->module->label() : $this->module,
            'channel_id' => $this->channel_id,
            'channel' => $this->whenLoaded('channel', function () {
                return $this->channel ? [
                    'id' => $this->channel->id,
                    'name' => $this->channel->name,
                    'chat_id' => $this->channel->chat_id,
                    'is_active' => (bool) $this->channel->is_active,
                ] : null;
            }),
            'command' => $this->command,
            'alias' => $this->alias,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'payload_template' => $this->payload_template,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
