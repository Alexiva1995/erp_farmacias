<?php

namespace App\Http\Controllers\Api;

use App\Exports\TraceabilityExport;
use App\Helpers\ApiResponse;
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
use App\Models\UserCyclicQuota;
use App\Http\Requests\InventoryCycle\StoreProductCountRequest;
use App\Http\Requests\InventoryCycle\StoreInvoiceCountRequest;
use App\Http\Requests\InventoryCycle\StoreSaleCountRequest;
use App\Http\Requests\InventoryCycle\ProcessCountActionRequest;
use App\Http\Requests\InventoryCycle\UpdateDiscrepancyRequest;
use App\Http\Requests\InventoryCycle\GetDailyQuotasMatrixRequest;
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

            $settings = \App\Models\GeneralSetting::first();
            $scope = $settings?->cyclic_inventory_scope ?? 'all';
            $dailyQuota = (int) ($settings?->cyclic_inventory_daily_quota ?? 50);

            $hasSearchFilters = $request->filled('q') || $request->filled('laboratoryId') || $request->filled('originId');

            $activeCycleId = \App\Models\InventoryCycle::where('status', 'active')->value('id');
            $user = Auth::user() ?? $request->user();
            $isAdmin = $user && ((int) $user->role_id === 1);

            if (!$isAdmin && $scope === 'quota' && !$hasSearchFilters && $dailyQuota > 0 && $activeCycleId) {
                $userId = $user?->id;
                $today = now()->toDateString();

                if ($userId) {
                    // Obtener la cuota activa del día para el usuario (el tier más alto)
                    $userQuota = UserCyclicQuota::where('user_id', $userId)
                        ->where('cycle_id', $activeCycleId)
                        ->where('quota_date', $today)
                        ->orderBy('quota_tier', 'desc')
                        ->first();

                    // Si no existe cuota inicial para hoy, generar el primer lote aleatorio de 50
                    if (!$userQuota) {
                        // Obtener IDs aleatorios de productos disponibles
                        $availableProductIds = (clone $query)->inRandomOrder()->limit($dailyQuota)->pluck('products.id')->toArray();
                        $userQuota = UserCyclicQuota::create([
                            'user_id'              => $userId,
                            'cycle_id'             => $activeCycleId,
                            'quota_date'           => $today,
                            'quota_tier'           => 1,
                            'assigned_quantity'    => count($availableProductIds),
                            'assigned_product_ids' => $availableProductIds,
                        ]);
                    }

                    $assignedIds = $userQuota->assigned_product_ids ?? [];
                    if (!empty($assignedIds)) {
                        $query->whereIn('products.id', $assignedIds);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                }
            }

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

    public function getUserQuotaStatus(Request $request)
    {
        try {
            $settings = \App\Models\GeneralSetting::first();
            $scope = $settings?->cyclic_inventory_scope ?? 'all';
            $dailyQuota = (int) ($settings?->cyclic_inventory_daily_quota ?? 50);
            $user = Auth::user() ?? $request->user();
            $isAdmin = $user && ((int) $user->role_id === 1);

            if ($isAdmin || $scope !== 'quota' || $dailyQuota <= 0) {
                return response()->json([
                    'is_active' => false,
                    'counted'   => 0,
                    'total'     => 0,
                    'tier'      => 1,
                    'can_request_more' => false,
                ]);
            }

            $activeCycleId = \App\Models\InventoryCycle::where('status', 'active')->value('id');
            if (!$activeCycleId) {
                return response()->json([
                    'is_active' => false,
                    'counted'   => 0,
                    'total'     => 0,
                    'tier'      => 1,
                    'can_request_more' => false,
                ]);
            }

            $userId = Auth::id() ?? $request->user()?->id;
            $today = now()->toDateString();

            $userQuota = UserCyclicQuota::where('user_id', $userId)
                ->where('cycle_id', $activeCycleId)
                ->where('quota_date', $today)
                ->orderBy('quota_tier', 'desc')
                ->first();

            $assignedIds = $userQuota ? ($userQuota->assigned_product_ids ?? []) : [];
            $totalAssigned = $userQuota ? (int) $userQuota->assigned_quantity : $dailyQuota;
            $currentTier = $userQuota ? (int) $userQuota->quota_tier : 1;

            // Contar cuántos productos del lote asignado ya fueron contados por el usuario
            $countedInCurrentBatch = 0;
            if (!empty($assignedIds)) {
                $countedInCurrentBatch = ProductCount::where('cycle_id', $activeCycleId)
                    ->where('user_id', $userId)
                    ->whereIn('product_id', $assignedIds)
                    ->count();
            }

            $canRequestMore = ($countedInCurrentBatch >= $totalAssigned) && ($totalAssigned > 0);

            return response()->json([
                'is_active'        => true,
                'counted'          => $countedInCurrentBatch,
                'total'            => $totalAssigned,
                'tier'             => $currentTier,
                'can_request_more' => $canRequestMore,
            ]);
        } catch (\Throwable $e) {
            Log::error('getUserQuotaStatus error: ' . $e->getMessage());
            return response()->json(['message' => 'Error al obtener estado de cuota'], 500);
        }
    }

    public function requestMoreQuotaProducts(Request $request)
    {
        try {
            $settings = \App\Models\GeneralSetting::first();
            $scope = $settings?->cyclic_inventory_scope ?? 'all';
            $dailyQuota = (int) ($settings?->cyclic_inventory_daily_quota ?? 50);

            $activeCycleId = \App\Models\InventoryCycle::where('status', 'active')->value('id');
            if (!$activeCycleId) {
                return response()->json(['message' => 'No hay un ciclo de inventario activo.'], 422);
            }

            $userId = Auth::id() ?? $request->user()?->id;
            $today = now()->toDateString();

            $lastQuota = UserCyclicQuota::where('user_id', $userId)
                ->where('cycle_id', $activeCycleId)
                ->where('quota_date', $today)
                ->orderBy('quota_tier', 'desc')
                ->first();

            $nextTier = $lastQuota ? ($lastQuota->quota_tier + 1) : 1;

            // Obtener productos disponibles en el ciclo que el usuario aún no haya contado
            $baseQuery = $this->inventoryCycleQueryService->getProductsFilteredQuery($request);
            $newProductIds = $baseQuery->inRandomOrder()->limit($dailyQuota)->pluck('products.id')->toArray();

            if (empty($newProductIds)) {
                return response()->json(['message' => 'No hay más productos pendientes por contar en este ciclo.'], 400);
            }

            $newQuota = UserCyclicQuota::create([
                'user_id'              => $userId,
                'cycle_id'             => $activeCycleId,
                'quota_date'           => $today,
                'quota_tier'           => $nextTier,
                'assigned_quantity'    => count($newProductIds),
                'assigned_product_ids' => $newProductIds,
            ]);

            $pointsPerCount = match (true) {
                $nextTier >= 3 => 4,
                $nextTier === 2 => 2,
                default => 1,
            };

            return response()->json([
                'message' => "¡Nuevo lote asignado! Nivel {$nextTier}: cada conteo te otorgará +{$pointsPerCount} puntos.",
                'data'    => $newQuota
            ]);
        } catch (\Throwable $e) {
            Log::error('requestMoreQuotaProducts error: ' . $e->getMessage());
            return response()->json(['message' => 'Error al solicitar más productos: ' . $e->getMessage()], 500);
        }
    }

    public function getDailyQuotasMatrix(GetDailyQuotasMatrixRequest $request)
    {
        try {
            $month = (int) $request->input('month', now()->month);
            $year = (int) $request->input('year', now()->year);
            $type = (string) $request->input('type', 'products');

            $data = $this->inventoryCycleQueryService->getDailyQuotasMatrixData($month, $year, $type);

            return ApiResponse::success(new \App\Http\Resources\DailyQuotasMatrixResource($data));
        } catch (\Throwable $e) {
            Log::error('getDailyQuotasMatrix error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ApiResponse::error('Error al obtener matriz de cuotas: ' . $e->getMessage(), 500);
        }
    }

    public function getProductCount(Request $request)
    {
        if ($request->filled('cycleId')) {
            $query = $this->inventoryCycleQueryService->getCycleDetailedCountsQuery($request);
        } else {
            $query = $this->inventoryCycleQueryService->getFilteredQuery($request);
        }

        $perPage = (int) $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            $data = $request->filled('cycleId')
                ? $this->formatCycleDetailItems($items)
                : $items;

            return response()->json([
                'data' => \App\Http\Resources\Inventory\CycleDetailProductResource::collection($data),
                'total' => $items->count()
            ]);
        }

        $paginatedResult = $query->paginate($perPage);
        $data = $request->filled('cycleId')
            ? $this->formatCycleDetailItems($paginatedResult->items())
            : $paginatedResult->items();

        return response()->json([
            'data' => \App\Http\Resources\Inventory\CycleDetailProductResource::collection($data),
            'total' => $paginatedResult->total()
        ]);
    }

    public function storeProductCount(StoreProductCountRequest $request, $productId)
    {
        $allowWithoutBarcode = $request->boolean('allow_without_barcode');
        $isSimple = \App\Models\GeneralSetting::first()?->cyclic_inventory_mode === 'simple';

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
                if ($isSimple) {
                    $productCount = $result['data'];
                    $approveData = [
                        'updated_lots' => $request->input('updated_lots', []),
                        'new_lots' => $request->input('new_lots', [])
                    ];
                    
                    $approveResult = $this->inventoryCycleActionService->processAction($productCount, 'approve', $approveData);
                    
                    if (!$approveResult['success']) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Error al procesar la aprobación automática del inventario: ' . $approveResult['message']
                        ], 400);
                    }

                    return response()->json([
                        'success' => true,
                        'message' => 'Conteo registrado y procesado exitosamente (Verificación Simple).',
                        'data' => $approveResult['data']
                    ], 201);
                }

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
                'id' => data_get($item, 'id'),
                'cycle_id' => data_get($item, 'cycle_id'),
                'product_id' => data_get($item, 'product_id'),
                'user_id' => data_get($item, 'user_id'),
                'supervisor_id' => data_get($item, 'supervisor_id'),
                'counted_quantity' => data_get($item, 'counted_quantity'),
                'system_quantity' => data_get($item, 'system_quantity'),
                'final_quantity' => data_get($item, 'counted_quantity'),
                'discrepancy' => data_get($item, 'discrepancy'),
                'status' => data_get($item, 'status'),
                'source_type' => data_get($item, 'source_type'),
                'created_at' => data_get($item, 'created_at'),
                'updated_at' => data_get($item, 'updated_at'),
                'product' => [
                    'id' => data_get($item, 'product_id'),
                    'name' => data_get($item, 'product_name', data_get($item, 'name')),
                    'photo_url' => data_get($item, 'product_photo_url', data_get($item, 'photo_url')),
                    'iva' => data_get($item, 'product_iva', data_get($item, 'iva')),
                    'psychotropic' => data_get($item, 'product_psychotropic', data_get($item, 'psychotropic')),
                    'unit_cost' => data_get($item, 'product_unit_cost', data_get($item, 'unit_cost', 0)),
                    'sale_price' => data_get($item, 'product_sale_price', data_get($item, 'sale_price', 0)),
                    'is_colombian_origin' => data_get($item, 'product_is_colombian_origin', data_get($item, 'is_colombian_origin')),
                    'active_ingredient' => data_get($item, 'product_active_ingredient', data_get($item, 'active_ingredient')),
                    'location' => data_get($item, 'product_location', data_get($item, 'location')),
                    'laboratory' => [
                        'name' => data_get($item, 'laboratory_name'),
                    ],
                ],
                'user' => [
                    'email' => data_get($item, 'user_email'),
                    'username' => data_get($item, 'user_username'),
                    'employee_name' => data_get($item, 'user_employee_name'),
                    'employee_last_name' => data_get($item, 'user_employee_last_name'),
                ],
                'supervisor' => [
                    'email' => data_get($item, 'supervisor_email'),
                    'username' => data_get($item, 'supervisor_username'),
                    'employee_name' => data_get($item, 'supervisor_employee_name'),
                    'employee_last_name' => data_get($item, 'supervisor_employee_last_name'),
                ],
            ];
        })->all();
    }

    public function processCountAction(ProcessCountActionRequest $request, $countId)
    {
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

    public function storeInvoiceCount(StoreInvoiceCountRequest $request, $productId)
    {
        // La validación es manejada por StoreInvoiceCountRequest
        $allowWithoutBarcode = $request->boolean('allow_without_barcode');

        try {
            $data = $request->validated();
            $data['allow_without_barcode'] = $allowWithoutBarcode;

            $result = $this->inventoryCycleActionService->createInvoiceCount($productId, $data);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], 201);
            }

            $statusCode = $result['message'] === 'Producto no encontrado.' ? 404 : 400;
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], $statusCode);

        } catch (\Exception $e) {
            Log::error('Error en controlador al registrar conteo de factura', [
                'product_id' => $productId,
                'error'      => $e->getMessage()
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

        // Optimización: Calcular totales sobre la BD mediante agregación SQL limpia sin arrastrar columnas individuales del SELECT
        $totalsQuery = clone $query;
        $totalsQuery->orders = null; // Eliminar ordenamiento
        
        $totalsRaw = $totalsQuery
            ->select(DB::raw("
                SUM(CASE WHEN (products.sale_price * discrepancies.discrepancy) > 0 THEN (products.sale_price * discrepancies.discrepancy) ELSE 0 END) as surplus,
                SUM(CASE WHEN (products.sale_price * discrepancies.discrepancy) < 0 THEN ABS(products.sale_price * discrepancies.discrepancy) ELSE 0 END) as shortage
            "))
            ->first();

        $surplus = (float) ($totalsRaw->surplus ?? 0);
        $shortage = (float) ($totalsRaw->shortage ?? 0);

        $totals = [
            'surplus' => $surplus,
            'shortage' => $shortage,
            'netTotal' => $surplus - $shortage,
        ];

        if ($perPage < 1) {
            $items = $query->get();

            return response()->json([
                'data' => $items,
                'total' => $items->count(),
                'totals' => $totals,
            ]);
        }

        $paginatedResult = $query->paginate($perPage);

        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total(),
            'totals' => $totals,
        ]);
    }

    public function closeActiveCycle(Request $request)
    {
        try {
            $rejectPending = $request->boolean('reject_pending');
            $result = $this->inventoryCycleActionService->closeActiveCycle($rejectPending);

            if ($result['success']) {
                return response()->json(['success' => true, 'message' => $result['message']]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'has_pending' => true,
                ], 400);
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
    public function getCycleSummary(\App\Http\Requests\Inventory\CycleSummaryRequest $request)
    {
        $query = $this->inventoryCycleQueryService->getCycleSummaryQuery($request);
        $perPage = (int) $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json([
                'data' => \App\Http\Resources\Inventory\CycleSummaryResource::collection($items),
                'total' => $items->count()
            ]);
        }

        $paginatedResult = $query->paginate($perPage);
        return response()->json([
            'data' => \App\Http\Resources\Inventory\CycleSummaryResource::collection($paginatedResult->items()),
            'total' => $paginatedResult->total()
        ]);
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

            $totals = $this->inventoryCycleQueryService->getCycleFinancialTotals($cycleId);

            return response()->json([
                'data' => $cycle,
                'totals' => $totals
            ]);
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

    public function storeSaleCount(StoreSaleCountRequest $request, $productId)
    {
        // La validación es manejada por StoreSaleCountRequest
        $allowWithoutBarcode = $request->boolean('allow_without_barcode');

        try {
            $data = $request->validated();
            $data['allow_without_barcode'] = $allowWithoutBarcode;

            $result = $this->inventoryCycleActionService->createSaleCount($productId, $data);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], 201);
            }

            $statusCode = $result['message'] === 'Producto no encontrado.' ? 404 : 400;
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], $statusCode);

        } catch (\Exception $e) {
            Log::error('Error en controlador al registrar conteo de venta', [
                'product_id' => $productId,
                'error'      => $e->getMessage()
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

    public function updateDiscrepancy(Request $request, $sourceType, $id)
    {
        // Solo administradores pueden editar discrepancias en el cierre
        if ((int) Auth::user()->role_id !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para realizar esta acción.'
            ], 403);
        }

        $request->validate([
            'discrepancy' => 'required|numeric'
        ]);

        try {
            $modelClass = null;
            switch ($sourceType) {
                case 'product_count':
                    $modelClass = \App\Models\ProductCount::class;
                    break;
                case 'invoice_count':
                    $modelClass = \App\Models\InvoiceCount::class;
                    break;
                case 'sale_count':
                    $modelClass = \App\Models\SaleCount::class;
                    break;
                default:
                    return response()->json(['success' => false, 'message' => 'Tipo de fuente no válido.'], 400);
            }

            $record = $modelClass::findOrFail($id);
            $result = $this->inventoryCycleActionService->updateDiscrepancy($record, $request->input('discrepancy'));

            if ($result['success']) {
                return response()->json($result);
            } else {
                return response()->json($result, 400);
            }

        } catch (\Exception $e) {
            Log::error('Error en updateDiscrepancy Controller', [
                'sourceType' => $sourceType,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage()
            ], 500);
        }
    }
}
