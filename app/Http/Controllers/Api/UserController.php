<?php

namespace App\Http\Controllers\Api;

use App\Contracts\User;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UserConfig;

class UserController extends Controller
{


    public function __construct(
        protected User $user
    ) {}

    public function getAll(): JsonResponse
    {

        $respuestaConsulta = $this->user->getAll();

        return ApiResponse::success($respuestaConsulta, "ok");
    }

    public function updateSortConfig(Request $request)
    {
        $request->validate([
        'sortBy' => 'required|string',
        'orderBy' => 'required|in:asc,desc',
    ]);

    $userId = Auth::id();
    if (!$userId) {
        return response()->json(['error' => 'No autorizado'], 401);
    }
    UserConfig::updateOrCreate(
        ['user_id' => $userId],
        ['sort_products_orders' => $request->sortBy . '|' . $request->orderBy]
    );

    return response()->json(['message' => 'Orden guardado']);
    }
}
