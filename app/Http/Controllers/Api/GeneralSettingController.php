<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeneralSetting;
use Illuminate\Http\Request;

class GeneralSettingController extends Controller
{
    public function index()
    {
        return GeneralSetting::firstOrCreate([], [
            'fiscal_mode' => 'demo',
            'special_taxpayer_status' => 'desactivada'
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'fiscal_mode' => 'required|string|in:demo,activa',
            'special_taxpayer_status' => 'required|string|in:activa,desactivada',
        ]);
        $setting = GeneralSetting::first();
        if ($setting) {
            $setting->update($request->all());
        } else {
            $setting = GeneralSetting::create($request->all());
        }

        return response()->json([
            'message' => 'Configuración guardada correctamente',
            'data' => $setting
        ]);
    }

}
