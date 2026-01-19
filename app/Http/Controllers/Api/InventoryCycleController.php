<?php

namespace App\Http\Controllers\Api;

use App\Exports\TraceabilityExport;
use App\Http\Controllers\Controller;
use App\Models\InvoiceCount;
use App\Models\Product;
use App\Models\ProductCount;
use App\Services\InventoryCycle\InventoryCycleQueryService;
use App\Services\InventoryCycle\InventoryCycleActionService;
use Exception;
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

    public function storeProductCount(Request $request, $productId)
    {
        $allowWithoutBarcode = $request->boolean('allow_without_barcode');
        
        $validationRules = [
            'counted_quantity' => 'required|numeric|min:0',
            'system_quantity' => 'required|numeric|min:0',
            'discrepancy' => 'required|numeric'
        ];

        // Solo requerir barcode si no se permite sin código de barras
        if (!$allowWithoutBarcode) {
            $validationRules['barcode'] = 'required|string';
        }

        $request->validate($validationRules);

        try {
            $data = [
                'counted_quantity' => $request->input('counted_quantity'),
                'system_quantity' => $request->input('system_quantity'),
                'discrepancy' => $request->input('discrepancy'),
                'allow_without_barcode' => $allowWithoutBarcode,
            ];

            // Solo incluir barcode si no se permite sin código de barras
            if (!$allowWithoutBarcode) {
                $data['barcode'] = $request->input('barcode');
            }

            $result = $this->inventoryCycleActionService->createProductCount($productId, $data);

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

    private function calculateCurrentStock(Product $product): int
    {
        return $this->inventoryCycleActionService->calculateCurrentStock($product);
    }

    public function processCountAction(Request $request, $countId)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'corrected_quantity' => 'nullable|numeric',
            'updated_lots' => 'nullable|array',
            'updated_lots.*.id' => 'required_with:updated_lots|integer|exists:product_lots,id',
            'updated_lots.*.quantity' => 'required_with:updated_lots|integer|min:0',
            'new_lots' => 'nullable|array',
            'new_lots.*.lot_number' => 'required_with:new_lots|string|max:255',
            'new_lots.*.expiration_date' => 'required_with:new_lots|date',
            'new_lots.*.quantity' => 'required_with:new_lots|integer|min:0',
        ]);

        try {
            $productCount = ProductCount::findOrFail($countId);
            $action = $request->input('action');
            $data = $request->only(['corrected_quantity', 'updated_lots', 'new_lots']);

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
            Log::error('Error en processCountAction', [
                'countId' => $countId,
                'request' => $request->all(),
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

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

    public function getInvoiceDetailsToCount(Request $request)
    {
        $query = $this->inventoryCycleQueryService->getInvoiceDetailsToCountQuery($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }

        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    public function storeInvoiceCount(Request $request, $productId)
    {
        $allowWithoutBarcode = $request->boolean('allow_without_barcode');
        
        $validationRules = [
            'counted_quantity' => 'required|numeric|min:0',
            'system_quantity' => 'required|numeric|min:0',
            'discrepancy' => 'required|numeric'
        ];

        // Solo requerir barcode si no se permite sin código de barras
        if (!$allowWithoutBarcode) {
            $validationRules['barcode'] = 'required|string';
        }

        $request->validate($validationRules);

        try {
            $data = $request->all();
            $data['allow_without_barcode'] = $allowWithoutBarcode;
            
            $result = $this->inventoryCycleActionService->createInvoiceCount($productId, $data);

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
            Log::error('Error en controlador al registrar conteo de factura', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor.'
            ], 500);
        }
    }

    public function getInvoiceCount(Request $request)
    {
        $query = $this->inventoryCycleQueryService->getInvoiceCountFilteredQuery($request);
        $perPage = $request->input('itemsPerPage', 10);

        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    public function processInvoiceCountAction(Request $request, $countId)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'corrected_quantity' => 'nullable|numeric',
            'updated_lots' => 'nullable|array',
            'updated_lots.*.id' => 'required_with:updated_lots|integer|exists:product_lots,id',
            'updated_lots.*.quantity' => 'required_with:updated_lots|integer|min:0',
            'new_lots' => 'nullable|array',
            'new_lots.*.lot_number' => 'required_with:new_lots|string|max:255',
            'new_lots.*.expiration_date' => 'required_with:new_lots|date',
            'new_lots.*.quantity' => 'required_with:new_lots|integer|min:0',
        ]);

        try {
            $invoiceCount = InvoiceCount::findOrFail($countId);
            $action = $request->input('action');
            $data = $request->only(['corrected_quantity', 'updated_lots', 'new_lots']);

            $result = $this->inventoryCycleActionService->processInvoiceCountAction($invoiceCount, $action, $data);

            if ($result['success']) {
                return response()->json(['success' => true, 'message' => $result['message'], 'data' => $result['data']], 200);
            } else {
                return response()->json(['success' => false, 'message' => $result['message']], 400);
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Registro de conteo de factura no encontrado.'], 404);
        } catch (\Exception $e) {
            Log::error('Error en processInvoiceCountAction', ['countId' => $countId, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }

    public function getCashCloseItems(Request $request)
    {
        $query = $this->inventoryCycleQueryService->getCashCloseItemsQuery($request);
        $perPage = $request->input('itemsPerPage', 10);
        $paginatedResult = $query->paginate($perPage);

        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total()
        ]);
    }

    public function closeActiveCycle(Request $request)
    {
        try {
            $result = $this->inventoryCycleActionService->closeActiveCycle();

            if ($result['success']) {
                return response()->json(['success' => true, 'message' => $result['message']]);
            } else {
                return response()->json(['success' => false, 'message' => $result['message']], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error al intentar cerrar el ciclo de inventario activo.', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Ocurrió un error inesperado en el servidor.'], 500);
        }
    }

    public function createCycle(Request $request)
    {
        $result = $this->inventoryCycleActionService->createNewCycle();
        if ($result['success']) {
            return response()->json(['success' => true, 'message' => $result['message']]);
        }
        return response()->json(['success' => false, 'message' => $result['message']], 400);
    }
    public function getCycleSummary(Request $request)
    {
        $query = $this->inventoryCycleQueryService->getCycleSummaryQuery($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }

        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }
    public function getCycleInfo($cycleId)
    {
        try {
            $cycle = DB::table('inventory_cycles')
                ->where('id', $cycleId)
                ->first();

            if (!$cycle) {
                return response()->json(['message' => 'Ciclo no encontrado'], 404);
            }

            return response()->json(['data' => $cycle]);
        } catch (Exception $e) {
            return response()->json(['message' => 'Error al obtener información del ciclo'], 500);
        }
    }

     public function getSaleDetailsToCount(Request $request)
    {
        $query = $this->inventoryCycleQueryService->getSalesDetailsToCountQuery($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }

        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

     public function storeSaleCount(Request $request, $productId)
    {
        $allowWithoutBarcode = $request->boolean('allow_without_barcode');
        
        $validationRules = [
            'counted_quantity' => 'required|numeric|min:0',
            'system_quantity' => 'required|numeric|min:0',
            'discrepancy' => 'required|numeric'
        ];

        // Solo requerir barcode si no se permite sin código de barras
        if (!$allowWithoutBarcode) {
            $validationRules['barcode'] = 'required|string';
        }

        $request->validate($validationRules);

        try {
            $data = $request->all();
            $data['allow_without_barcode'] = $allowWithoutBarcode;
            
            $result = $this->inventoryCycleActionService->createSaleCount($productId, $data);

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
            Log::error('Error en controlador al registrar conteo de venta', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor.'
            ], 500);
        }
    }
    
}
