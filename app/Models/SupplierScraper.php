<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScraperKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierScraper extends Model
{
    use HasFactory;

    protected $table = 'supplier_scrapers';

    protected $fillable = [
        'supplier_id',
        'scraper_key',
        'portal_url',
        'username',
        'password',
        'auth_payload',
        'is_active',
        'last_scraped_at',
        'last_status',
        'last_message',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'scraper_key' => ScraperKey::class,
        'auth_payload' => 'array',
        'is_active' => 'boolean',
        'last_scraped_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Obtiene la contraseña descifrada del scraper.
     */
    public function getDecryptedPassword(): ?string
    {
        if (empty($this->password)) {
            return null;
        }

        try {
            return \App\Helpers\FtpCrypt::decrypt((string) $this->password);
        } catch (\Throwable) {
            return (string) $this->password;
        }
    }

    /**
     * Obtiene el token de sesión almacenado en auth_payload si existe.
     */
    public function getSessionToken(): ?string
    {
        $payload = is_array($this->auth_payload) ? $this->auth_payload : [];
        $token = $payload['session_token'] ?? null;

        return is_string($token) && trim($token) !== '' ? trim($token) : null;
    }
}
