<?php

declare(strict_types=1);

namespace App\Http\Resources\Configuration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientImportExecutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'clients_created'   => (int) ($this['clients_created'] ?? 0),
            'clients_updated'   => (int) ($this['clients_updated'] ?? 0),
            'clients_unchanged' => (int) ($this['clients_unchanged'] ?? 0),
            'total_processed'   => (int) ($this['total_processed'] ?? 0),
        ];
    }
}
