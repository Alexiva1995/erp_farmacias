<?php

namespace App\Helpers;

use Illuminate\Encryption\Encrypter;
use Illuminate\Contracts\Encryption\DecryptException;

class FtpCrypt
{
    protected static function getEncrypter(?string $rawKey = null): Encrypter
    {
        $rawKey = $rawKey ?? env('FTP_SECRET_KEY', '');
        $key = substr(hash('sha256', (string) $rawKey), 0, 32);
        return new Encrypter($key, 'AES-256-CBC');
    }

    public static function encrypt(?string $plain): string
    {
        if (empty($plain)) return '';
        return self::getEncrypter()->encryptString($plain);
    }

    public static function decrypt(?string $encrypted): string
    {
        if (empty($encrypted)) return '';

        try {
            // 1. Intentar con la clave previa original
            return self::getEncrypter(env('FTP_SECRET_KEY', ''))->decryptString($encrypted);
        } catch (\Throwable $e) {
            try {
                // 2. Intentar con APP_KEY
                return self::getEncrypter(config('app.key'))->decryptString($encrypted);
            } catch (\Throwable $ex) {
                // 3. Fallback a texto crudo
                return $encrypted;
            }
        }
    }
}