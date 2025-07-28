<?php

namespace App\Http\Controllers\Api;

use App\Exports\TraceabilityExport;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCount;
use App\Services\InventoryCycle\InventoryCycleQueryService;
use App\Services\InventoryCycle\InventoryCycleActionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class InventoryCycleController extends Controller
{
    public function __construct(
        private InventoryCycleQueryService $inventoryCycleQueryService,
        private InventoryCycleActionService $inventoryCycleActionService
    ) {
    }

    /**
     * Obtiene productos para la vista de inventario
     */
    public function getProductsForInventory(Request $request)
    {
        $query = $this->inventoryCycleQueryService->getProductsFilteredQuery($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }

        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    public function getProductCount(Request $request)
    {
        $query = $this->inventoryCycleQueryService->getFilteredQuery($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    /**
     * Registra un nuevo conteo de inventario para un producto
     */
    public function storeProductCount(Request $request, $productId)
    {
        $request->validate([
            'barcode' => 'required|string',
            'counted_quantity' => 'required|numeric|min:0',
            'system_quantity' => 'required|numeric|min:0', // Validar que venga del frontend
            'discrepancy' => 'required|numeric'
        ]);

        try {
            // --- AJUSTE AQUÍ: Pasar system_quantity al servicio ---
            $result = $this->inventoryCycleActionService->createProductCount($productId, [
                'barcode' => $request->input('barcode'),
                'counted_quantity' => $request->input('counted_quantity'),
                'system_quantity' => $request->input('system_quantity'), // Pasar el valor recibido
                'discrepancy' => $request->input('discrepancy')
            ]);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], 201);
            } else {
                $statusCode = $result['message'] === 'Producto no encontrado.' ? 404 : 400;

                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], $statusCode);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error en controlador al registrar conteo', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor.'
            ], 500);
        }
    }

    /**
     * Calcula el stock actual del producto basado en lotes válidos
     */
    private function calculateCurrentStock(Product $product): int
    {
        // Este método se movió al servicio
        // Se mantiene aquí solo por compatibilidad si se usa en otros lugares
        return $this->inventoryCycleActionService->calculateCurrentStock($product);
    }

    /**
     * Procesa la acción de aprobar o rechazar un conteo de inventario
     */
    public function processCountAction(Request $request, $countId)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'corrected_quantity' => 'nullable|numeric',
            // --- AJUSTE AQUÍ: Validar un objeto 'lot', no un array 'lots' ---
            'lot' => 'nullable|array',
            'lot.lot_id' => 'required_with:lot|integer|exists:product_lots,id',
            'lot.quantity' => 'required_with:lot|integer|min:0',
        ]);

        try {
            $productCount = ProductCount::findOrFail($countId);
            $action = $request->input('action');

            $data = $request->only(['corrected_quantity', 'lot']);

            $result = $this->inventoryCycleActionService->processAction($productCount, $action, $data);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registro de conteo no encontrado.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene las estadísticas de conteos
     */
    public function getCountStatistics()
    {
        try {
            $statistics = $this->inventoryCycleActionService->getCountStatistics();

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica si existe un ciclo activo y devuelve su información
     */
    public function getActiveCycleStatus()
    {
        try {
            $activeCycle = $this->inventoryCycleActionService->getActiveCycleInfo();

            if ($activeCycle) {
                return response()->json([
                    'success' => true,
                    'has_active_cycle' => true,
                    'data' => [
                        'id' => $activeCycle->id,
                        'start_date' => $activeCycle->start_date,
                        'end_date' => $activeCycle->end_date,
                        'status' => $activeCycle->status,
                        'days_remaining' => $activeCycle->end_date ?
                            now()->diffInDays($activeCycle->end_date, false) : null
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'has_active_cycle' => false,
                    'message' => 'No existe un ciclo de inventario activo.',
                    'data' => null
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar el ciclo activo: ' . $e->getMessage()
            ], 500);
        }
    }
}
