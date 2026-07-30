<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LaboratoryManageResource extends JsonResource
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
            'group_id' => $this->group_id,
            'group' => $this->whenLoaded('group', function () {
                return $this->group ? [
                    'id' => $this->group->id,
                    'name' => $this->group->name,
                ] : null;
            }),
            'products_count' => $this->products_count ?? 0,
        ];
    }
}
