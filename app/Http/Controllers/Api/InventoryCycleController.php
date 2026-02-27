<?php

namespace App\Http\Controllers\Api;

use App\Exports\TraceabilityExport;
use App\Http\Controllers\Controller;
use App\Models\InvoiceCount;
use App\Models\Product;
use App\Models\ProductCount;
use App\Models\SaleCount;
use App\Services\InventoryCycle\InventoryCycleQueryService;
use App\Services\InventoryCycle\InventoryCycleActionService;
use App\Models\InventoryMovement;
use App\Models\ProductLot;
use App\Models\ProductDistribution;
use App\Models\InvoiceCountDistribution;
use App\Models\SaleCountDistribution;
use App\Models\InventoryCycle;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class InventoryCycleController extends Controller
{
    public function __construct(
        private InventoryCycleQueryService $inventoryCycleQueryService,
        private InventoryCycleActionService $inventoryCycleActionService
    ) {
    }

    public function getProductsForInventory(Request $request)
    {
        try {
            $query = $this->inventoryCycleQueryService->getProductsFilteredQuery($request);
            $perPage = (int) $request->input('itemsPerPage', 10);

            if ($perPage < 1) {
                $items = $query->get();
                return response()->json(['data' => $items, 'total' => $items->count()]);
            }

            $paginatedResult = $query->paginate($perPage);
            return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
        } catch (\Throwable $e) {
            Log::error('getProductsForInventory error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'message' => 'Error al obtener productos: ' . $e->getMessage(),
                'data' => [],
                'total' => 0,
            ], 500);
        }
    }

    public function getProductCount(Request $request)
    {
        if ($request->filled('cycleId')) {
            $query = $this->inventoryCycleQueryService->getCycleDetailedCountsQuery($request);
        } else {
            $query = $this->inventoryCycleQueryService->getFilteredQuery($request);
        }

        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            $data = $request->filled('cycleId')
                ? $this->formatCycleDetailItems($items)
                : $items;

            return response()->json(['data' => $data, 'total' => $items->count()]);
        }

        $paginatedResult = $query->paginate($perPage);
        $data = $request->filled('cycleId')
            ? $this->formatCycleDetailItems($paginatedResult->items())
            : $paginatedResult->items();

        return response()->json(['data' => $data, 'total' => $paginatedResult->total()]);
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

    private function formatCycleDetailItems($items)
    {
        return collect($items)->map(function ($item) {
            return [
                'id' => $item->id,
                'cycle_id' => $item->cycle_id,
                'product_id' => $item->product_id,
                'user_id' => $item->user_id,
                'supervisor_id' => $item->supervisor_id,
                'counted_quantity' => $item->counted_quantity,
                'system_quantity' => $item->system_quantity,
                'final_quantity' => $item->counted_quantity,
                'discrepancy' => $item->discrepancy,
                'status' => $item->status,
                'source_type' => $item->source_type,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'product' => [
                    'id' => $item->product_id,
                    'name' => $item->product_name,
                    'photo_url' => $item->product_photo_url,
                    'iva' => $item->product_iva,
                    'psychotropic' => $item->product_psychotropic,
                    'is_colombian_origin' => $item->product_is_colombian_origin,
                    'laboratory' => [
                        'name' => $item->laboratory_name,
                    ],
                ],
                'user' => [
                    'email' => $item->user_email,
                    'username' => $item->user_username,
                    'employee_name' => $item->user_employee_name,
                    'employee_last_name' => $item->user_employee_last_name,
                ],
                'supervisor' => [
                    'email' => $item->supervisor_email,
                    'username' => $item->supervisor_username,
                    'employee_name' => $item->supervisor_employee_name,
                    'employee_last_name' => $item->supervisor_employee_last_name,
                ],
            ];
        })->all();
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

    public function getUsersWithCounts()
    {
        try {
            $activeCycleId = \App\Models\InventoryCycle::where('status', 'active')->value('id');

            if (!$activeCycleId) {
                return response()->json([]);
            }

            // Obtener usuarios únicos que han hecho conteos O supervisado en el ciclo activo
            $users = \App\Models\User::query()
                ->select('users.id', 'users.username', 'users.email')
                ->selectRaw('employees.name as employee_name, employees.last_name as employee_last_name')
                ->join('product_counts', 'users.id', '=', 'product_counts.user_id')
                ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
                ->where('product_counts.cycle_id', $activeCycleId)
                ->when(Auth::user()->role !== 'admin', function ($query) {
                    $query->where('users.id', '!=', Auth::id());
                })
                ->union(
                    \App\Models\User::query()
                        ->select('users.id', 'users.username', 'users.email')
                        ->selectRaw('employees.name as employee_name, employees.last_name as employee_last_name')
                        ->join('invoices_counts', 'users.id', '=', 'invoices_counts.user_id')
                        ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
                        ->where('invoices_counts.cycle_id', $activeCycleId)
                )
                ->union(
                    \App\Models\User::query()
                        ->select('users.id', 'users.username', 'users.email')
                        ->selectRaw('employees.name as employee_name, employees.last_name as employee_last_name')
                        ->join('sales_counts', 'users.id', '=', 'sales_counts.user_id')
                        ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
                        ->where('sales_counts.cycle_id', $activeCycleId)
                )
                ->union(
                    \App\Models\User::query()
                        ->select('users.id', 'users.username', 'users.email')
                        ->selectRaw('employees.name as employee_name, employees.last_name as employee_last_name')
                        ->join('product_counts', 'users.id', '=', 'product_counts.supervisor_id')
                        ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
                        ->where('product_counts.cycle_id', $activeCycleId)
                        ->whereNotNull('product_counts.supervisor_id')
                )
                ->union(
                    \App\Models\User::query()
                        ->select('users.id', 'users.username', 'users.email')
                        ->selectRaw('employees.name as employee_name, employees.last_name as employee_last_name')
                        ->join('invoices_counts', 'users.id', '=', 'invoices_counts.supervisor_id')
                        ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
                        ->where('invoices_counts.cycle_id', $activeCycleId)
                        ->whereNotNull('invoices_counts.supervisor_id')
                )
                ->union(
                    \App\Models\User::query()
                        ->select('users.id', 'users.username', 'users.email')
                        ->selectRaw('employees.name as employee_name, employees.last_name as employee_last_name')
                        ->join('sales_counts', 'users.id', '=', 'sales_counts.supervisor_id')
                        ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
                        ->where('sales_counts.cycle_id', $activeCycleId)
                        ->whereNotNull('sales_counts.supervisor_id')
                )
                ->distinct()
                ->orderBy('employee_name')
                ->orderBy('employee_last_name')
                ->get();

            return response()->json($users);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener usuarios: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getInvoiceDetailsToCount(Request $request)
    {
        try {
            $query = $this->inventoryCycleQueryService->getInvoiceDetailsToCountQuery($request);
            $perPage = (int) $request->input('itemsPerPage', 10);

            if ($perPage < 1) {
                $items = $query->get();
                return response()->json(['data' => $items, 'total' => $items->count()]);
            }

            $paginatedResult = $query->paginate($perPage);
            return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
        } catch (\Throwable $e) {
            Log::error('getInvoiceDetailsToCount error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'message' => 'Error al obtener productos de factura: ' . $e->getMessage(),
                'data' => [],
                'total' => 0,
            ], 500);
        }
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
        $perPage = (int) $request->input('itemsPerPage', 10);

        // Calcular totales sobre TODOS los registros (no solo la página actual)
        $totalsQuery = $this->inventoryCycleQueryService->getCashCloseItemsQuery($request);
        $allItems = $totalsQuery->get();

        $totals = [
            'surplus' => 0,
            'shortage' => 0,
            'netTotal' => 0
        ];

        foreach ($allItems as $item) {
            $amount = ($item->product_sale_price ?? 0) * $item->discrepancy;
            if ($amount > 0) {
                $totals['surplus'] += $amount;
            } else {
                $totals['shortage'] += abs($amount);
            }
        }

        $totals['netTotal'] = $totals['surplus'] - $totals['shortage'];
        if ($perPage < 1) {
            $items = $query->get();

            return response()->json([
                'data' => $items,
                'total' => $items->count(),
                'totals' => $this->calculateCashCloseTotals($items),
            ]);
        }

        $paginatedResult = $query->paginate($perPage);
        $totalsItems = $this->inventoryCycleQueryService->getCashCloseItemsQuery($request)->get();

        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total(),
            'totals' => $this->calculateCashCloseTotals($totalsItems),
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
        try {
            $query = $this->inventoryCycleQueryService->getSalesDetailsToCountQuery($request);
            $perPage = (int) $request->input('itemsPerPage', 10);

            if ($perPage < 1) {
                $items = $query->get();
                return response()->json(['data' => $items, 'total' => $items->count()]);
            }

            $paginatedResult = $query->paginate($perPage);
            return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
        } catch (\Throwable $e) {
            Log::error('getSaleDetailsToCount error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'message' => 'Error al obtener productos de punto de venta: ' . $e->getMessage(),
                'data' => [],
                'total' => 0,
            ], 500);
        }
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

    public function getSaleCount(Request $request)
    {
        $query = $this->inventoryCycleQueryService->getSaleCountFilteredQuery($request);
        $perPage = $request->input('itemsPerPage', 10);

        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }


    public function processSaleCountAction(Request $request, $countId)
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
            $saleCount = SaleCount::findOrFail($countId);
            $action = $request->input('action');
            $data = $request->only(['corrected_quantity', 'updated_lots', 'new_lots']);

            $result = $this->inventoryCycleActionService->processSaleCountAction($saleCount, $action, $data);

            if ($result['success']) {
                return response()->json(['success' => true, 'message' => $result['message'], 'data' => $result['data']], 200);
            } else {
                return response()->json(['success' => false, 'message' => $result['message']], 400);
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Registro de conteo de punto de venta no encontrado.'], 404);
        } catch (\Exception $e) {
            Log::error('Error en processSaleCountAction', ['countId' => $countId, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error interno del servidor.'], 500);
        }
    }

    private function calculateCashCloseTotals($items): array
    {
        $totals = [
            'surplus' => 0,
            'shortage' => 0,
            'netTotal' => 0,
        ];

        foreach ($items as $item) {
            $amount = ($item->product_sale_price ?? 0) * $item->discrepancy;

            if ($amount > 0) {
                $totals['surplus'] += $amount;
            } else {
                $totals['shortage'] += abs($amount);
            }
        }

        $totals['netTotal'] = $totals['surplus'] - $totals['shortage'];

        return $totals;
    }

    public function deleteCount($sourceType, $id)
    {
        return DB::transaction(function () use ($sourceType, $id) {
            try {
                $modelClass = null;
                $distClass = null;
                $distKey = null;

                switch ($sourceType) {
                    case 'product_count':
                        $modelClass = \App\Models\ProductCount::class;
                        $distClass = \App\Models\ProductDistribution::class;
                        $distKey = 'product_count_id';
                        break;
                    case 'invoice_count':
                        $modelClass = \App\Models\InvoiceCount::class;
                        $distClass = \App\Models\InvoiceCountDistribution::class;
                        $distKey = 'invoice_count_id';
                        break;
                    case 'sale_count':
                        $modelClass = \App\Models\SaleCount::class;
                        $distClass = \App\Models\SaleCountDistribution::class;
                        $distKey = 'sale_count_id';
                        break;
                    default:
                        return response()->json(['success' => false, 'message' => 'Tipo de fuente no válido.'], 400);
                }

                $record = $modelClass::findOrFail($id);

                // Verificar si tiene distribuciones (trazabilidad directa)
                $hasDistributions = $distClass::where($distKey, $id)->exists();

                // Verificar movimientos de inventario cercanos en el tiempo
                $hasMovements = InventoryMovement::where('product_id', $record->product_id)
                    ->whereIn('movement_type', ['adjustment', 'loss'])
                    ->whereBetween('created_at', [
                        Carbon::parse($record->updated_at)->subSeconds(30),
                        Carbon::parse($record->updated_at)->addSeconds(30)
                    ])
                    ->exists();

                if ($hasDistributions || $hasMovements) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se puede eliminar: este registro tiene movimientos de trazabilidad asociados.'
                    ], 422);
                }

                // Finalmente borrar el registro del conteo
                $record->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Registro eliminado correctamente.'
                ]);

            } catch (\Exception $e) {
                Log::error('Error eliminando registro de cierre:', [
                    'sourceType' => $sourceType,
                    'id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Error al eliminar el registro: ' . $e->getMessage()
                ], 500);
            }
        });
    }
}
