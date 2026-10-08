<?php

declare(strict_types=1);

namespace App\Services\Configuration;

use App\Contracts\Repositories\GeneralSettingRepositoryInterface;
use App\Models\GeneralSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GeneralSettingService
{
    /**
     * @var GeneralSettingRepositoryInterface
     */
    protected $repository;

    /**
     * Constructor del servicio.
     *
     * @param GeneralSettingRepositoryInterface $repository
     */
    public function __construct(GeneralSettingRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Obtener la configuración general.
     *
     * @return GeneralSetting
     */
    public function getSettings(): GeneralSetting
    {
        return $this->repository->getSettings();
    }

    /**
     * Actualizar la configuración, manejando la subida de archivos.
     *
     * @param array $data
     * @return GeneralSetting
     */
    public function updateSettings(array $data): GeneralSetting
    {
        if (isset($data['app_logo']) && $data['app_logo'] instanceof UploadedFile) {
            $data['app_logo'] = $this->uploadFile($data['app_logo'], 'branding');
        }

        if (isset($data['app_favicon']) && $data['app_favicon'] instanceof UploadedFile) {
            $data['app_favicon'] = $this->uploadFile($data['app_favicon'], 'branding');
        }

        if (isset($data['app_signature_stamp']) && $data['app_signature_stamp'] instanceof UploadedFile) {
            $data['app_signature_stamp'] = $this->uploadFile($data['app_signature_stamp'], 'branding');
        }

        if (isset($data['hero_image']) && $data['hero_image'] instanceof UploadedFile) {
            $data['hero_image'] = $this->uploadFile($data['hero_image'], 'branding');
        }

        if (isset($data['section2_image']) && $data['section2_image'] instanceof UploadedFile) {
            $data['section2_image'] = $this->uploadFile($data['section2_image'], 'branding');
        }

        if (isset($data['section3_image']) && $data['section3_image'] instanceof UploadedFile) {
            $data['section3_image'] = $this->uploadFile($data['section3_image'], 'branding');
        }

        if (isset($data['enabled_telegram_views']) && is_array($data['enabled_telegram_views'])) {
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('telegram_commands')) {
                    $allModules = ['generales', 'farmacia', 'restaurante', 'cosmeticos', 'alquileres'];
                    foreach ($allModules as $mod) {
                        if (!in_array($mod, $data['enabled_telegram_views'], true)) {
                            \App\Models\TelegramCommand::where('module', $mod)->update(['is_active' => false]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Registro silencioso seguro si las tablas no existen
            }
        }

        return $this->repository->updateSettings($data);
    }

    /**
     * Subir un archivo al almacenamiento público.
     *
     * @param UploadedFile $file
     * @param string $folder
     * @return string
     */
    protected function uploadFile(UploadedFile $file, string $folder): string
    {
        $path = $file->store($folder, 'public');

        if (function_exists('tenancy') && tenancy()->initialized && function_exists('tenant_asset')) {
            return tenant_asset($path);
        }

        return Storage::url($path);
    }
}
