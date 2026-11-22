<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

trait ResolvesPhotoUrl
{
    /**
     * Resuelve la URL pública (central o multi-tenant) del campo photo_url.
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                $url = $this->attributes['photo_url'] ?? null;
                if (!$url) {
                    return null;
                }

                if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                    return $url;
                }

                // Limpiar prefijo de storage para evitar rutas duplicadas
                if (str_starts_with($url, '/storage/')) {
                    $url = substr($url, 9);
                } elseif (str_starts_with($url, 'storage/')) {
                    $url = substr($url, 8);
                }

                if (function_exists('tenancy') && tenancy()->initialized && function_exists('tenant_asset')) {
                    return tenant_asset($url);
                }

                return Storage::url($url);
            }
        );
    }
}
