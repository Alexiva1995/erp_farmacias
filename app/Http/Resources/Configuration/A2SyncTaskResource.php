<?php

declare(strict_types=1);

namespace App\Http\Resources\Configuration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class A2SyncTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'section'       => $this->section,
            'year'          => $this->year,
            'status'        => $this->status,
            'preview_data'  => $this->preview_data,
            'error_message' => $this->error_message,
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
