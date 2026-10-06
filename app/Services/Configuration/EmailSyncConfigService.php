<?php

namespace App\Services\Configuration;

use App\Helpers\FtpCrypt;
use App\Models\GeneralSetting;
use App\Services\Email\GmailImapService;
use App\Services\Suppliers\SupplierEmailCatalogService;
use Exception;
use Illuminate\Support\Carbon;

class EmailSyncConfigService
{
    public function __construct(
        protected GmailImapService $imapService,
        protected SupplierEmailCatalogService $emailCatalogService,
    ) {
    }

    /**
     * Obtiene el registro de configuración general o crea uno base.
     */
    public function getConfig(): GeneralSetting
    {
        return GeneralSetting::firstOrCreate([], [
            'gmail_sync_host' => 'imap.gmail.com',
            'gmail_sync_port' => 993,
            'gmail_sync_folder' => 'INBOX',
            'gmail_sync_enabled' => false,
        ]);
    }

    /**
     * Actualiza la configuración de sincronización de correo de Gmail.
     */
    public function updateConfig(array $data): GeneralSetting
    {
        $setting = $this->getConfig();

        $updateData = [
            'gmail_sync_email' => $data['gmail_sync_email'] ?? $setting->gmail_sync_email,
            'gmail_sync_host' => $data['gmail_sync_host'] ?? $setting->gmail_sync_host ?? 'imap.gmail.com',
            'gmail_sync_port' => !empty($data['gmail_sync_port']) ? (int) $data['gmail_sync_port'] : ($setting->gmail_sync_port ?? 993),
            'gmail_sync_folder' => $data['gmail_sync_folder'] ?? $setting->gmail_sync_folder ?? 'INBOX',
            'gmail_sync_enabled' => isset($data['gmail_sync_enabled']) ? (bool) $data['gmail_sync_enabled'] : ($setting->gmail_sync_enabled ?? false),
        ];

        // Cifrar contraseña de aplicación únicamente si fue proporcionada y no está vacía
        if (!empty($data['gmail_sync_password'])) {
            // Eliminar posibles espacios en blanco si el usuario pegó la contraseña de Google agrupada de 4 en 4
            $cleanPassword = str_replace(' ', '', trim((string) $data['gmail_sync_password']));
            $updateData['gmail_sync_password'] = FtpCrypt::encrypt($cleanPassword);
        }

        $setting->update($updateData);

        return $setting->fresh();
    }

    /**
     * Prueba la conexión en vivo con el servidor IMAP de Gmail.
     */
    public function testConnection(array $params = []): array
    {
        $setting = $this->getConfig();

        $email = !empty($params['gmail_sync_email']) ? trim($params['gmail_sync_email']) : ($setting->gmail_sync_email ?: config('mail_sync.email'));

        if (!empty($params['gmail_sync_password'])) {
            $password = str_replace(' ', '', trim((string) $params['gmail_sync_password']));
        } elseif (!empty($setting->gmail_sync_password)) {
            $password = FtpCrypt::decrypt($setting->gmail_sync_password);
        } else {
            $password = config('mail_sync.password');
        }

        $host = !empty($params['gmail_sync_host']) ? trim($params['gmail_sync_host']) : ($setting->gmail_sync_host ?: (config('mail_sync.host') ?: 'imap.gmail.com'));
        $port = !empty($params['gmail_sync_port']) ? (int) $params['gmail_sync_port'] : (int) ($setting->gmail_sync_port ?: (config('mail_sync.port') ?: 993));
        $folder = !empty($params['gmail_sync_folder']) ? trim($params['gmail_sync_folder']) : ($setting->gmail_sync_folder ?: (config('mail_sync.folder') ?: 'INBOX'));

        if (empty($email)) {
            throw new Exception("Debe ingresar la dirección de correo electrónico de Gmail.");
        }

        if (empty($password)) {
            throw new Exception("Debe ingresar la Contraseña de Aplicación de 16 letras generada en Google.");
        }

        try {
            $this->imapService->connect($host, $port, $email, $password, 15);
            $folderInfo = $this->imapService->selectFolder($folder);
            $this->imapService->disconnect();

            $setting->update([
                'gmail_sync_last_tested_at' => Carbon::now(),
                'gmail_sync_last_status' => 'success',
            ]);

            return [
                'success' => true,
                'message' => "Conexión IMAP establecida con éxito. Carpeta '{$folder}' accesible ({$folderInfo['exists']} correos encontrados).",
                'tested_at' => Carbon::now()->toISOString(),
                'folder' => $folderInfo,
            ];
        } catch (\Throwable $e) {
            $setting->update([
                'gmail_sync_last_tested_at' => Carbon::now(),
                'gmail_sync_last_status' => 'error',
            ]);

            throw new Exception("Error al conectar con Gmail IMAP: " . $e->getMessage());
        }
    }

    /**
     * Ejecuta la sincronización manual de catálogos de proveedores por correo.
     */
    public function syncCatalogs(): array
    {
        return $this->emailCatalogService->syncEmailCatalogs(false);
    }
}
