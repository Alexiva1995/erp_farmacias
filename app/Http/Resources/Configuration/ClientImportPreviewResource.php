<?php

declare(strict_types=1);

namespace App\Http\Resources\Configuration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientImportPreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'summary'         => $this['summary'] ?? [],
            'matched_clients' => $this['matched_clients'] ?? [],
            'new_clients'     => $this['new_clients'] ?? [],
        ];
    }
}
