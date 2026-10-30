<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BootstrapConfigController extends Controller
{
    /**
     * Devuelve la configuración de arranque de módulos y tipo de negocio del ERP.
     */
    public function index(): JsonResponse
    {
        $businessType = \App\Models\GeneralSetting::first()?->business_type ?? env('BUSINESS_TYPE', 'pharmacy');
        $enabledModules = \App\Providers\ModuleServiceProvider::getEnabledModules();

        $isTenant = function_exists('tenancy') && tenancy()->initialized;

        $tenantVertical = null;
        if ($isTenant && tenancy()->tenant) {
            $tenantVertical = tenancy()->tenant->vertical;
        }

        $vertical = $tenantVertical ?: $businessType;

        return response()->json([
            'status' => 'success',
            'data' => [
                'business_type' => $businessType,
                'vertical' => $vertical,
                'enabled_modules' => $enabledModules,
                'is_tenant' => $isTenant,
                'is_master' => !$isTenant,
            ]
        ]);
    }
}
