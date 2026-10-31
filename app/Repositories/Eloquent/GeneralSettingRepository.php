<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\GeneralSettingRepositoryInterface;
use App\Models\GeneralSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

        // Asegurar que la columna enable_zoom exista en la tabla actual (central o tenant)
        if (array_key_exists('enable_zoom', $data) && !Schema::hasColumn('general_settings', 'enable_zoom')) {
            try {
                Schema::table('general_settings', function (Blueprint $table) {
                    $table->boolean('enable_zoom')->default(false)->after('enable_flash_checkout');
                });
            } catch (\Throwable $e) {
                Log::warning('No se pudo crear automáticamente la columna enable_zoom en general_settings: ' . $e->getMessage());
            }
        }

        // Asegurar que las columnas de Cashea existan en la tabla actual (central o tenant)
        if (array_key_exists('enable_cashea', $data) && !Schema::hasColumn('general_settings', 'enable_cashea')) {
            try {
                Schema::table('general_settings', function (Blueprint $table) {
                    $table->boolean('enable_cashea')->default(false)->after('enable_zoom');
                    $table->string('cashea_mode')->default('manual')->after('enable_cashea');
                    $table->string('cashea_environment')->default('sandbox')->after('cashea_mode');
                    $table->string('cashea_client_id')->nullable()->after('cashea_environment');
                    $table->string('cashea_public_key')->nullable()->after('cashea_client_id');
                    $table->text('cashea_private_key')->nullable()->after('cashea_public_key');
                    $table->unsignedTinyInteger('cashea_installments_count')->default(3)->after('cashea_private_key');
                    $table->unsignedSmallInteger('cashea_installment_days')->default(14)->after('cashea_installments_count');
                });
            } catch (\Throwable $e) {
                Log::warning('No se pudo crear automáticamente las columnas de Cashea en general_settings: ' . $e->getMessage());
            }
        }

        // Filtrar solo los campos que existan como columnas físicas en la tabla
        try {
            $existingColumns = Schema::getColumnListing('general_settings');
            if (!empty($existingColumns)) {
                $data = array_intersect_key($data, array_flip($existingColumns));
            }
        } catch (\Throwable $e) {
            Log::warning('Error obteniendo listado de columnas de general_settings: ' . $e->getMessage());
        }

        $settings->update($data);
        return $settings;
    }
}
