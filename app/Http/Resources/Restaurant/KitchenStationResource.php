<?php

declare(strict_types=1);

namespace App\Http\Resources\Restaurant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KitchenStationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'color' => $this->color ?? 'primary',
            'icon' => $this->icon ?? 'tabler-chef-hat',
            'sort_order' => (int) ($this->sort_order ?? 1),
            'pos_x' => (float) ($this->pos_x ?? 0),
            'pos_y' => (float) ($this->pos_y ?? 0),
            'queued_count' => (int) ($this->queued_count ?? 0),
            'preparing_count' => (int) ($this->preparing_count ?? 0),
            'ready_count' => (int) ($this->ready_count ?? 0),
            'active_items' => $this->active_items ?? [],
        ];
    }
}
