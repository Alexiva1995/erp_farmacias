<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierScraperResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authPayload = is_array($this->auth_payload) ? $this->auth_payload : [];

        return [
            'id' => $this->id,
            'supplier_id' => $this->supplier_id,
            'scraper_key' => $this->scraper_key instanceof \App\Enums\ScraperKey
                ? $this->scraper_key->value
                : (string) $this->scraper_key,
            'portal_url' => $this->portal_url,
            'username' => $this->username,
            'has_password' => !empty($this->password),
            'session_token' => $authPayload['session_token'] ?? null,
            'has_session_token' => !empty($authPayload['session_token']),
            'is_active' => (bool) ($this->is_active ?? true),
            'last_scraped_at' => $this->last_scraped_at?->toIso8601String(),
            'last_status' => $this->last_status,
            'last_message' => $this->last_message,
        ];
    }
}
