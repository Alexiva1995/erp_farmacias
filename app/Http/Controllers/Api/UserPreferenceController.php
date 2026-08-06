<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserPreferenceController extends Controller
{
    /**
     * Actualiza una o varias preferencias de UI del usuario autenticado.
     * Body: { "key": "cash_wallets_compact", "value": true }
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'key'   => ['required', 'string', 'max:80'],
            'value' => ['required'],
        ]);

        $user = Auth::user();

        $preferences = $user->ui_preferences ?? [];
        $preferences[$request->string('key')->toString()] = $request->input('value');

        $user->update(['ui_preferences' => $preferences]);

        return ApiResponse::success(['ui_preferences' => $user->fresh()->ui_preferences]);
    }

    /**
     * Retorna todas las preferencias de UI del usuario autenticado.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'ui_preferences' => Auth::user()->ui_preferences ?? [],
        ]);
    }
}
