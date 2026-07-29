<?php

namespace App\Helpers;

use Illuminate\Encryption\Encrypter;
use Illuminate\Contracts\Encryption\DecryptException;

class FtpCrypt
{
    protected static function getEncrypter(): Encrypter
    {
        $rawKey = env('FTP_SECRET_KEY', config('app.key'));
        $key = substr(hash('sha256', (string) $rawKey), 0, 32); // AES-256 requiere 32 bytes
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
            return self::getEncrypter()->decryptString($encrypted);
        } catch (DecryptException $e) {
            // Si la clave no se puede desencriptar (ej: estaba en texto plano o cambio FTP_SECRET_KEY), devolver texto crudo
            return $encrypted;
        } catch (\Throwable $e) {
            return $encrypted;
        }
    }
}