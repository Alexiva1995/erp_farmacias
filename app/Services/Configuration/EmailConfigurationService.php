<?php

declare(strict_types=1);

namespace App\Services\Configuration;

use App\Models\GeneralSetting;
use App\Services\Ecommerce\EcommerceMailService;
use Exception;

class EmailConfigurationService
{
    protected EcommerceMailService $mailService;

    public function __construct(EcommerceMailService $mailService)
    {
        $this->mailService = $mailService;
    }

    /**
     * Obtener la configuración actual de correo y plantillas.
     */
    public function getConfiguration(): GeneralSetting
    {
        $setting = GeneralSetting::first();
        if (!$setting) {
            $setting = GeneralSetting::create([
                'mail_mailer' => 'smtp',
                'mail_port' => 587,
                'mail_encryption' => 'tls',
            ]);
        }

        return $setting;
    }

    /**
     * Actualizar la configuración de correo y plantillas.
     */
    public function updateConfiguration(array $data): GeneralSetting
    {
        $setting = $this->getConfiguration();

        // Si se envió mail_password vacío o null y ya existía contraseña, conservarla
        if (array_key_exists('mail_password', $data) && ($data['mail_password'] === '' || $data['mail_password'] === null)) {
            if (!empty($setting->mail_password)) {
                unset($data['mail_password']);
            }
        }

        $setting->update($data);

        return $setting;
    }

    /**
     * Enviar correo de prueba para verificar conectividad SMTP.
     */
    public function testSmtpConnection(string $testEmail): array
    {
        return $this->mailService->sendTestMail($testEmail);
    }
}
