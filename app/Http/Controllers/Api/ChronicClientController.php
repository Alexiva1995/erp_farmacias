<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Chronic\ChronicClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChronicClientController extends Controller
{
    public function __construct(
        protected ChronicClientService $chronicService
    ) {
    }

    /**
     * Listar pacientes crónicos con filtros y paginación.
     */
    public function index(Request $request): JsonResponse
    {
        $paginated = $this->chronicService->getChronicPatients($request);

        return ApiResponse::success([
            'items' => $paginated->items(),
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'per_page' => $paginated->perPage(),
            'last_page' => $paginated->lastPage(),
        ], 'Pacientes crónicos obtenidos exitosamente', 200);
    }

    /**
     * Obtener estadísticas de pacientes y tratamientos crónicos.
     */
    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        $stats = $this->chronicService->getStats($userId);

        return ApiResponse::success($stats, 'Estadísticas obtenidas exitosamente', 200);
    }

    /**
     * Obtener catálogo de productos con configuración de consumo y frecuencia.
     */
    public function productsConfig(Request $request): JsonResponse
    {
        $paginated = $this->chronicService->getProductConsumptionConfig($request);

        return ApiResponse::success([
            'items' => $paginated->items(),
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'per_page' => $paginated->perPage(),
            'last_page' => $paginated->lastPage(),
        ], 'Configuración de consumo de productos obtenida exitosamente', 200);
    }

    /**
     * Actualizar configuración de consumo y frecuencia de un producto.
     */
    public function updateProductConfig(\App\Http\Requests\UpdateProductConsumptionRequest $request, int $id): JsonResponse
    {
        try {
            $product = $this->chronicService->updateProductConsumption($id, $request->validated());
            return ApiResponse::success($product, 'Configuración de producto actualizada exitosamente', 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Producto no encontrado.', 404);
        }
    }

    /**
     * Ejecutar sincronización y detección de medicamentos crónicos mediante IA.
     */
    public function syncAi(): JsonResponse
    {
        $result = $this->chronicService->syncChronicProductsWithAi();

        return ApiResponse::success($result, 'Sincronización de productos crónicos con IA completada exitosamente', 200);
    }

    /**
     * Marcar seguimiento de paciente como contactado/enviado por WhatsApp.
     */
    public function markContacted(\App\Http\Requests\MarkChronicContactedRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = $request->user()?->id;
        $result = $this->chronicService->markAsContacted(
            (int) $validated['client_id'],
            $validated['product_ids'] ?? [],
            $userId
        );

        return ApiResponse::success($result, 'Seguimiento registrado exitosamente', 200);
    }

    /**
     * Verificar disponibilidad de un paciente antes de contactar por WhatsApp.
     */
    public function checkAvailability(int $clientId): JsonResponse
    {
        $result = $this->chronicService->checkAvailability($clientId);

        return ApiResponse::success($result, 'Disponibilidad verificada', 200);
    }

    /**
     * Matriz mensual de cumplimiento de cuotas diarias de fidelización.
     */
    public function dailyQuotasMatrix(Request $request): JsonResponse
    {
        $month = (int) $request->input('month', now()->month);
        $year  = (int) $request->input('year', now()->year);

        $data = $this->chronicService->getDailyFidelityQuotasMatrixData($month, $year);

        return ApiResponse::success($data, 'Matriz de cuotas de fidelización obtenida exitosamente', 200);
    }

    /**
     * Remover/resetear teléfono de un cliente cuando no posee WhatsApp o es inválido.
     */
    public function removePhone(int $clientId): JsonResponse
    {
        try {
            $result = $this->chronicService->removeInvalidPhone($clientId);

            return ApiResponse::success($result, 'Teléfono removido exitosamente', 200);
        } catch (\Throwable $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}