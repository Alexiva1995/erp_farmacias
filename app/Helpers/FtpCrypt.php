<?php

namespace App\Helpers;

use Illuminate\Encryption\Encrypter;

class FtpCrypt
{
    protected static function getEncrypter(): Encrypter
    {
        $rawKey = env('FTP_SECRET_KEY');
        $key = substr(hash('sha256', $rawKey), 0, 32); // AES-256 requiere 32 bytes
        return new Encrypter($key, 'AES-256-CBC');
    }

    public static function encrypt(string $plain): string
    {
        return self::getEncrypter()->encryptString($plain);
    }

    public static function decrypt(string $encrypted): string
    {
        return self::getEncrypter()->decryptString($encrypted);
    }
}