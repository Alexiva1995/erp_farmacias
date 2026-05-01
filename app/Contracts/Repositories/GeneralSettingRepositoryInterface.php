<?php

namespace App\Contracts\Repositories;

use App\Models\GeneralSetting;

interface GeneralSettingRepositoryInterface
{
    /**
     * Obtener la configuración general única.
     *
     * @return GeneralSetting
     */
    public function getSettings(): GeneralSetting;

    /**
     * Actualizar la configuración general.
     *
     * @param array $data
     * @return GeneralSetting
     */
    public function updateSettings(array $data): GeneralSetting;
}
