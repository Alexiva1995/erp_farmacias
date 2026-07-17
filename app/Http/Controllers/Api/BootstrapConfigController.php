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
        // Leer el tipo de negocio directamente desde la base de datos (general_settings)
        $settings = \DB::table('general_settings')->first();
        $businessType = $settings ? $settings->business_type : null;
        
        if (!$businessType) {
            $businessType = config('app.business_type', 'pharmacy');
        }

        // Configurar los módulos habilitados según el tipo de negocio
        $enabledModules = [$businessType];
        if ($businessType === 'sports_rental') {
            $enabledModules = ['reservation'];
        } else if ($businessType === 'pharmacy') {
            $enabledModules = ['pharmacy'];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'business_type' => $businessType,
                'enabled_modules' => $enabledModules,
            ]
        ]);
    }
}
