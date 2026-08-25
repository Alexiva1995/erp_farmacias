<?php

namespace App\Helpers;

use Illuminate\Encryption\Encrypter;
use Illuminate\Contracts\Encryption\DecryptException;

class FtpCrypt
{
    private const DEFAULT_KEY = 'e6891c6c01292c1b82b8f999e8ef65102ff683994cbc2534eca0e47aa0cad0c3';

    protected static function getEncrypter(?string $rawKey = null): Encrypter
    {
        $rawKey = $rawKey ?? config('services.ftp.secret_key') ?? env('FTP_SECRET_KEY') ?? self::DEFAULT_KEY;
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

        // 1. Intentar con la clave de servicios/env/fallback
        try {
            $key = config('services.ftp.secret_key') ?? env('FTP_SECRET_KEY') ?? self::DEFAULT_KEY;
            return self::getEncrypter($key)->decryptString($encrypted);
        } catch (\Throwable $e) {
            // continuar
        }

        // 2. Intentar con la clave por defecto directamente
        try {
            return self::getEncrypter(self::DEFAULT_KEY)->decryptString($encrypted);
        } catch (\Throwable $e) {
            // continuar
        }

        // 3. Intentar con APP_KEY bajo Encrypter personalizado
        try {
            return self::getEncrypter(config('app.key'))->decryptString($encrypted);
        } catch (\Throwable $e) {
            // continuar
        }

        // 4. Intentar con Crypt nativo de Laravel
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