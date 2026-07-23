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

        return response()->json([
            'status' => 'success',
            'data' => [
                'business_type' => $businessType,
                'enabled_modules' => $enabledModules,
            ]
        ]);
    }
}
