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
        $businessType = config('app.business_type', 'pharmacy');
        
        $enabledModulesString = config('app.enabled_modules', 'pharmacy');
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
