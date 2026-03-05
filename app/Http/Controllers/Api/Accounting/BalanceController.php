<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\BalanceService;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;

class BalanceController extends Controller
{
    public function __construct(
        private BalanceService $balanceService
    ) {}

    /**
     * Obtiene el balance general consolidado
     */
    public function index(): JsonResponse
    {
        try {
            $data = $this->balanceService->getFullBalance();
            return ApiResponse::success($data, 'Balance general obtenido con éxito.');
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Error al calcular el balance general: ' . $e->getMessage(),
                500
            );
        }
    }
}
