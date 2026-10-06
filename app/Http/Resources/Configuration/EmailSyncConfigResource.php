<?php

namespace App\Http\Resources\Configuration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailSyncConfigResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'gmail_sync_email' => $this->gmail_sync_email ?? config('mail_sync.email') ?? '',
            'has_password' => !empty($this->gmail_sync_password) || !empty(config('mail_sync.password')),
            'gmail_sync_host' => $this->gmail_sync_host ?: (config('mail_sync.host') ?: 'imap.gmail.com'),
            'gmail_sync_port' => (int) ($this->gmail_sync_port ?: (config('mail_sync.port') ?: 993)),
            'gmail_sync_folder' => $this->gmail_sync_folder ?: (config('mail_sync.folder') ?: 'INBOX'),
            'gmail_sync_enabled' => (bool) ($this->gmail_sync_enabled ?? false),
            'gmail_sync_last_tested_at' => $this->gmail_sync_last_tested_at ? $this->gmail_sync_last_tested_at->toISOString() : null,
            'gmail_sync_last_status' => $this->gmail_sync_last_status,
        ];
    }
}
