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
        $businessType = env('BUSINESS_TYPE', 'pharmacy');
        
        $enabledModulesString = env('ENABLED_MODULES', 'pharmacy');
        $enabledModules = array_map('trim', explode(',', strtolower($enabledModulesString)));

        return response()->json([
            'status' => 'success',
            'data' => [
                'business_type' => $businessType,
                'enabled_modules' => $enabledModules,
            ]
        ]);
    }
}
