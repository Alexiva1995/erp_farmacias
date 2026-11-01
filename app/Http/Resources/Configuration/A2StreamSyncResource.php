<?php

declare(strict_types=1);

namespace App\Http\Resources\Configuration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class A2StreamSyncResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => 'success',
            'stats'  => $this->resource['stats'] ?? [],
            'synced_at' => now()->toIso8601String(),
        ];
    }
}
