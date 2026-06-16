<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\GeneralSettingRepositoryInterface;
use App\Models\GeneralSetting;

class GeneralSettingRepository implements GeneralSettingRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function getSettings(): GeneralSetting
    {
        return GeneralSetting::firstOrCreate([], [
            'fiscal_mode' => 'demo',
            'special_taxpayer_status' => 'desactivada',
            'app_name' => 'Tova - Cerebro Operativo',
            'footer_text' => 'Todos los derechos reservados de Tova'
        ]);
    }

    /**
     * @inheritDoc
     */
    public function updateSettings(array $data): GeneralSetting
    {
        $settings = $this->getSettings();
        $settings->update($data);
        return $settings;
    }
}
