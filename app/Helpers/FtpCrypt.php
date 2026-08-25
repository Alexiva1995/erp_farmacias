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

        // 1. Intentar con la clave FTP_SECRET_KEY
        try {
            return self::getEncrypter(env('FTP_SECRET_KEY', ''))->decryptString($encrypted);
        } catch (\Throwable $e) {
            // continuar
        }

        // 2. Intentar con APP_KEY bajo Encrypter personalizado
        try {
            return self::getEncrypter(config('app.key'))->decryptString($encrypted);
        } catch (\Throwable $e) {
            // continuar
        }

        // 3. Intentar con Crypt nativo de Laravel
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            // continuar
        }

        // 4. Si no es un string cifrado (ej: texto plano ingresado directamente o clave antigua)
        // Verificar si parece un payload base64 JSON de Laravel Crypt o Encrypter
        $decoded = @json_decode(base64_decode($encrypted, true) ?: '', true);
        if (!$decoded || !isset($decoded['iv'], $decoded['value'], $decoded['mac'])) {
            // Es texto plano directamente
            return $encrypted;
        }

        \Illuminate\Support\Facades\Log::error('[FtpCrypt] Fallo al descifrar la credencial/token de integración. Verifique las llaves de cifrado.');
        throw new \RuntimeException('No se pudo descifrar la credencial de integración. Por favor guarde nuevamente las credenciales en la configuración del proveedor.');
    }
}