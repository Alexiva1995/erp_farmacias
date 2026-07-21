<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Contracts\Client;
use App\Helpers\ApiResponse;
use App\Services\Order\OrderActionService;
use App\Services\Order\OrderQueryService;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderTotalsRequest;
use App\Http\Requests\Order\AddOrderItemRequest;
use App\Http\Requests\Order\CompleteOrderRequest;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\Auth;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Exceptions\InsufficientStockException;
use App\Contracts\Order as OrderContract;

class OrderController extends Controller
{

    public function __construct(
        protected Client $client,
        protected OrderContract $orderContract,
        private OrderActionService $orderActionService,
        private OrderQueryService $orderQueryService,
    ) {
    }
    public function index(Request $request)
    {
        $perPage = (int) $request->input('itemsPerPage', 10);
        $page = max(1, (int) $request->input('page', 1));

        // Query de conteo (sin ORDER BY — MySQL rechaza COUNT en subqueries con ORDER BY sin LIMIT)
        $countQuery = $this->orderQueryService->getCountQueryProduct($request);
        $total = $countQuery->count();

        // Query de datos (con ORDER BY y paginación)
        $dataQuery = $this->orderQueryService->getFilteredQueryProduct($request);

        if ($perPage < 1) {
            $items = $dataQuery->get();
            app(\App\Services\Order\OrderActionService::class)->applyGeneralPromotionsToProducts($items);
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }

        $offset = ($page - 1) * $perPage;
        $items = $dataQuery->skip($offset)->take($perPage)->get();

        // Aplicar promociones generales activas
        app(\App\Services\Order\OrderActionService::class)->applyGeneralPromotionsToProducts($items);

        return response()->json([
            'data' => $items,
            'total' => $total
        ]);
    }


    public function consultByIdentification(Request $request)
    {
        $buscarPorIdentificaion = $this->client->consultByIdentification($request->Identification);
        
        // Si no se encuentra y la identificacion es el cliente generico, crearlo
        if (!$buscarPorIdentificaion && $request->Identification === '99999999') {
            try {
                $buscarPorIdentificaion = \App\Models\Client::create([
                    'identification' => '99999999',
                    'identification_type' => 'V-',
                    'name' => 'Consumidor',
                    'last_name' => 'Final',
                    'email' => 'consumidorfinal@tova.com',
                    'phone' => '0000000000',
                    'address' => 'Consumidor Final',
                ]);
            } catch (\Exception $e) {
                Log::error("Error al crear cliente genérico automático: " . $e->getMessage());
            }
        }

        if (!$buscarPorIdentificaion) {
            return ApiResponse::success([
                'found' => false,
                'identification_searched' => $request->Identification
            ], "the client not found", 200);
        }

        // Si es el cliente generico, forzar el nombre a Consumidor Final
        if ($request->Identification === '99999999') {
            $buscarPorIdentificaion->name = "Consumidor";
            $buscarPorIdentificaion->last_name = "Final";
        }

        return ApiResponse::success([
            'found' => true,
            'client' => $buscarPorIdentificaion,
        ], "successfully", 200);
    }

    public function store(StoreOrderRequest $request)
    {
        try {
            $order = $this->orderActionService->createOrder($request->validated());
            return ApiResponse::success([
                'order' => $order->toArray()
            ], 'Orden creada exitosamente.', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al crear la orden: ' . $e->getMessage(), 500);
        }
    }

    public function getMyOpenOrder(Request $request)
    {
        try {
            $sellerId = $request->query('seller_id') ?: Auth::id();
            if (!$sellerId) {
                return ApiResponse::error('Vendedor no autenticado.', 401);
            }

            $openOrder = $this->orderActionService->getMyOpenOrder($sellerId);
            $foreignOrdersCount = $this->orderQueryService->getForeignOrdersCount();

            if ($openOrder) {
                return ApiResponse::success([
                    'order' => $openOrder,
                    'foreign_orders_count' => $foreignOrdersCount
                ], "Orden abierta de vendedor encontrada.", 200);
            } else {
                return ApiResponse::success([
                    'order' => null,
                    'foreign_orders_count' => $foreignOrdersCount
                ], "No se encontró orden abierta para el vendedor.", 200);
            }
        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener la orden: ' . $e->getMessage(), 500);
        }
    }


    public function storeOrderItem(AddOrderItemRequest $request, Order $order)
    {
        $validatedData = $request->validated();
        try {
            $orderItem = $this->orderActionService->addUpdateOrderItems($order, $validatedData);
            return ApiResponse::success([
                'order_item' => $orderItem->toArray()
            ], 'Producto agregado/actualizado en la orden exitosamente.', 201);
        } catch (InsufficientStockException $e) {
            return ApiResponse::error(
                $e->getMessage(),
                400,
                [
                    'available_stock' => $e->getAvailableStock(),
                    'requested_quantity' => $e->getRequestedQuantity(),
                    'product_name' => $e->getProductName(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Error en OrderItemController@store: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('Ocurrió un error interno del servidor al agregar el producto.', 500);
        }
    }



    public function updateOrderTotals(UpdateOrderTotalsRequest $request, Order $order)
    {
        try {

            $query = $this->orderActionService->updateordenCurrency($order, $request->validated());
            return response()->json([
                'message' => 'Orden y detalles actualizado exitosamente.',
                'data' => [
                    'order' => $query->fresh(['details.product']),
                ]
            ], 200);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar la orden: ' . $e->getMessage(), 500);
        }
    }

    public function deleteOrderDetail(Order $order, OrderDetail $item)
    {
        if ($item->order_id !== $order->id) {
            return ApiResponse::error('El detalle del producto no pertenece a esta orden.', 403);
        }

        try {
            $this->orderActionService->deleteDetail($order, $item);
            return ApiResponse::success('Producto eliminado de la orden exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al eliminar producto de la orden:', ['error' => $e->getMessage(), 'order_id' => $order->id, 'order_detail_id' => $item->id]);
            return ApiResponse::error('Error al eliminar el producto: ' . $e->getMessage(), 500);
        }
    }

    public function abandonOrder(Order $order)
    {
        if ($order->status !== 'Pending') {
            return ApiResponse::error('Solo se pueden abandonar órdenes abiertas.', 400);
        }
        try {
            $abandonedOrder = $this->orderActionService->abandonOrder($order);
            return ApiResponse::success('Orden abandonada exitosamente.', ['order' => $abandonedOrder]);
        } catch (\Exception $e) {
            Log::error('Error al abandonar la orden:', ['error' => $e->getMessage(), 'order_id' => $order->id]);
            return ApiResponse::error('No se pudo abandonar la orden: ' . $e->getMessage(), 500);
        }
    }

    public function completeOrder(Order $orderId, CompleteOrderRequest $request)
    {
        if ($orderId->details()->doesntExist()) {
            return ApiResponse::error('No hay productos en la orden', 400);
        }

        try {
            $sellerId = Auth::id();
            $result = $this->orderActionService->complete($orderId, $request, $sellerId);
            return ApiResponse::success($result, 'Compra finalizada exitosamente.', 200);
        } catch (\App\Exceptions\PaymentDiscrepancyException $e) {
            return $e->render($request);
        } catch (InsufficientStockException $e) {
            Log::warning('Stock insuficiente al completar orden', [
                'order_id' => $orderId->id,
                'product' => $e->getProductName(),
                'available' => $e->getAvailableStock(),
                'requested' => $e->getRequestedQuantity(),
            ]);
            return ApiResponse::error($e->getMessage(), 422, [
                'available_stock' => $e->getAvailableStock(),
                'requested_quantity' => $e->getRequestedQuantity(),
                'product_name' => $e->getProductName(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Error al completar la orden', [
                'order_id' => $orderId->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('No se pudo completar la orden. Por favor, intente de nuevo.', 500);
        }
    }

    public function getcompletedOrder(Request $request)
    {
        $this->applySellerFilterForVendedor($request);
        $query = $this->orderQueryService->getFilteredQuery($request, 'Completed');
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    public function getAllOrder(Request $request)
    {
        $this->applySellerFilterForVendedor($request);
        $query = $this->orderQueryService->getFilteredQuery($request, 'all');
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    public function getAbandonedOrder(Request $request)
    {
        $this->applySellerFilterForVendedor($request);
        $query = $this->orderQueryService->getFilteredQuery($request, 'Abandoned');
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    public function getCancelledOrder(Request $request)
    {
        $this->applySellerFilterForVendedor($request);
        $query = $this->orderQueryService->getFilteredQuery($request, 'Cancelled');
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }


    public function getCPrintOrder(int $orderId)
    {
        $order = Order::with('details.product.laboratory', 'details.dish', 'details.court', 'client', 'seller')->find($orderId);
        if (!$order) {
            return ApiResponse::error('Orden no encontrada.', 404);
        }

        $hasCreditPayment = collect($order->payment_methods)->contains(function ($payment) {
            return $payment['method'] === 'credit';
        });

        return ApiResponse::success([
            'order' => $order,
            'hasCreditPayment' => $hasCreditPayment,
        ], "Datos de la orden recuperados correctamente", 200);
    }

    public function filtrarOrderPorpsychotropicsConPaginacion(Request $request): JsonResponse
    {
        $filtros = [
            "itemsPerPage" => $request->itemsPerPage,
            "page" => $request->page,
        ];


        if ($request->filled("orderBy") && $request->filled("sortBy")) {
            $filtros["orderBy"] = $request->orderBy;
            $filtros["sortBy"] = $request->sortBy;
        }

        $repuesta = $this->orderContract->filtrarOrdenesWithPsychotropicsforPaginate($filtros);

        return ApiResponse::success($repuesta, "OK", 200);
    }

    public function reserveOrder(Order $order): JsonResponse
    {
        $sellerId = Auth::id();
        $generalSettings = DB::table('general_settings')->first();
        $isRestaurant = $generalSettings && $generalSettings->business_type === 'restaurant';

        if (!$isRestaurant) {
            $existingReservedOrder = Order::where('seller_id', $sellerId)
                ->where('status', 'Reserved')
                ->first();

            if ($existingReservedOrder) {
                return ApiResponse::error('Ya tienes una orden reservada.', 409);
            }
        }

        if ($order->status !== 'Pending') {
            return ApiResponse::error('Solo se pueden reservada órdenes abiertas.', 400);
        }

        try {
            $order = $this->orderActionService->reserveOrder($order, $sellerId);
            return ApiResponse::success($order, 'Orden reservada exitosamente.', 200);
        } catch (\Exception $e) {
            Log::error('Error al reservada la orden:', ['error' => $e->getMessage(), 'order_id' => $order->id]);
            return ApiResponse::error('No se pudo reservada la orden: ' . $e->getMessage(), 500);
        }
    }

    public function reserveAddOrder(Order $order): JsonResponse
    {
        try {
            $sellerId = Auth::id();
            $order = $this->orderActionService->reserveAndAddOrder($order, $sellerId);
            return ApiResponse::success($order, 'Orden agregada exitosamente.', 200);
        } catch (\Exception $e) {
            Log::error('Error al agregar la orden:', ['error' => $e->getMessage(), 'order_id' => $order->id]);
            return ApiResponse::error('No se pudo agregar la orden: ' . $e->getMessage(), 500);
        }
    }
    public function getDebitoFiscal(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date'
            ]);

            $startDate = $request->start_date ?? now()->startOfMonth()->format('Y-m-d');
            $endDate = $request->end_date ?? now()->endOfMonth()->format('Y-m-d');

            // Asegurar que la fecha inicial no sea menor a 2026
            if ($startDate < '2026-01-01') {
                $startDate = '2026-01-01';
            }

            $debitoFiscalData = $this->orderQueryService->getDebitoFiscal($startDate, $endDate);

            return ApiResponse::success([
                'periodo' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'debito_fiscal' => $debitoFiscalData['total_debito'],
                'detalle_debito' => [
                    'total_orders_with_iva'  => $debitoFiscalData['total_records'],
                    'total_iva_amount'       => $debitoFiscalData['total_iva_amount'],
                    'total_spe_amount'       => $debitoFiscalData['total_spe_amount'] ?? 0,
                    'total_spe_sales_amount' => $debitoFiscalData['total_spe_sales_amount'] ?? 0,
                    'total_spe_count'        => $debitoFiscalData['total_spe_count'] ?? 0,
                ]
            ], 'Débito fiscal obtenido exitosamente');

        } catch (\Exception $e) {
            Log::error('Error al obtener débito fiscal: ' . $e->getMessage());
            return ApiResponse::error('Error al obtener débito fiscal: ' . $e->getMessage(), 500);
        }
    }
    public function getFiscalHistoryData(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
                'page' => 'nullable|integer',
                'itemsPerPage' => 'nullable|integer',
                'sortBy' => 'nullable|string',
                'orderBy' => 'nullable|string'
            ]);

            $startDate = $request->start_date ?? now()->startOfMonth()->format('Y-m-d');
            $endDate = $request->end_date ?? now()->endOfMonth()->format('Y-m-d');

            // Asegurar que la fecha inicial no sea menor a 2026
            if ($startDate < '2026-01-01') {
                $startDate = '2026-01-01';
            }
            $page = $request->page ?? 1;
            $itemsPerPage = $request->itemsPerPage ?? 10;
            if ($itemsPerPage == -1) {
                $itemsPerPage = 1000; // Limite si pide "Todas"
            }
            $sortBy = $request->sortBy ?? 'invoice_date';
            $orderBy = $request->orderBy ?? 'desc';

            $fiscalData = $this->orderQueryService->getFiscalHistoryRecords(
                $startDate,
                $endDate,
                $page,
                $itemsPerPage,
                $sortBy,
                $orderBy
            );

            return ApiResponse::success($fiscalData, 'Registros de fiscal history obtenidos exitosamente');

        } catch (\Exception $e) {
            Log::error('Error al obtener registros de fiscal history: ' . $e->getMessage());
            return ApiResponse::error('Error al obtener registros de fiscal history: ' . $e->getMessage(), 500);
        }
    }

    public function cancelledOrder(Order $order)
    {
        if (in_array(strtolower($order->status), ['cancelled', 'abandoned'])) {
            return ApiResponse::error('La orden ya se encuentra cancelada o abandonada.', 400);
        }
        try {
            $abandonedOrder = $this->orderActionService->cancelledOrder($order);
            return ApiResponse::success(['order' => $abandonedOrder], 'Orden cancelada exitosamente.');
        } catch (\Exception $e) {
            Log::error('Error al cancelar la orden:', ['error' => $e->getMessage(), 'order_id' => $order->id]);
            return ApiResponse::error('No se pudo cancelar la orden: ' . $e->getMessage(), 500);
        }
    }

    public function getSearchReserved(): JsonResponse
    {
        try {
            $sellerId = Auth::id();
            if (!$sellerId) {
                return ApiResponse::error('Vendedor no autenticado.', 401);
            }

            $result = DB::transaction(function () use ($sellerId) {
                $pendingOrder = Order::where('seller_id', $sellerId)
                    ->where('status', Order::PENDING)
                    ->withCount('details')
                    ->first();

                if ($pendingOrder && $pendingOrder->details_count > 0) {
                    return ['success' => false, 'message' => 'Ya tienes una orden pendiente activa con productos.', 'status' => 400];
                }

                // Si hay una orden pendiente pero está vacía, la eliminamos para cargar la nueva
                if ($pendingOrder) {
                    $pendingOrder->delete();
                }

                $order = Order::where('seller_id', $sellerId)
                    ->where('status', Order::RESERVED)
                    ->lockForUpdate()
                    ->first();

                if ($order) {
                    $order->status = Order::PENDING;
                    $order->save();
                    return ['success' => true, 'message' => 'Orden actualizada.', 'status' => 200];
                }

                return ['success' => false, 'message' => 'No se encontró ninguna orden reservada.', 'status' => 404];
            });

            if ($result['success']) {
                return response()->json(['message' => $result['message']], $result['status']);
            }
            return response()->json(['message' => $result['message']], $result['status']);
        } catch (\Exception $e) {
            Log::error('Error en getSearchReserved', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('Ocurrió un error al procesar la orden reservada. Intente de nuevo.', 500);
        }
    }

    public function getReservedOrders(Request $request): JsonResponse
    {
        try {
            $sellerId = Auth::id();
            if (!$sellerId) {
                return ApiResponse::error('Vendedor no autenticado.', 401);
            }

            $generalSettings = DB::table('general_settings')->first();
            $isSportsRental = $generalSettings && $generalSettings->business_type === 'sports_rental';

            if ($isSportsRental) {
                // Para alquiler deportivo, obtenemos las reservaciones del día actual que no sean canceladas
                $today = now()->format('Y-m-d');
                $carbonToday = now();
                $dayOfWeek = $carbonToday->dayOfWeekIso;

                $reservations = \App\Models\Reservation::where('date', $today)
                    ->whereIn('status', ['pending', 'verified'])
                    ->with(['court', 'client'])
                    ->get();

                // Obtener los horarios fijos para este día de la semana que no tengan excepción
                $fixedSchedules = \App\Models\FixedSchedule::where('day_of_week', $dayOfWeek)
                    ->whereDoesntHave('exceptions', function ($query) use ($today) {
                        $query->where('date', $today);
                    })
                    ->with(['court'])
                    ->get();

                // Mapear los horarios fijos para que tengan una estructura similar a las reservaciones
                $mappedFixed = $fixedSchedules->map(function ($fixed) use ($today) {
                    return [
                        'id' => 'fixed_' . $fixed->id,
                        'court_id' => $fixed->court_id,
                        'date' => $today,
                        'start_time' => $fixed->start_time,
                        'end_time' => $fixed->end_time,
                        'client_name' => $fixed->client_name,
                        'client_whatsapp' => $fixed->client_whatsapp,
                        'status' => 'verified', // Las fijas se consideran verificadas
                        'is_fixed' => true,
                        'court' => $fixed->court,
                        'client' => null,
                    ];
                });

                // Unificar y ordenar todo por start_time cronológicamente
                $unified = $reservations->concat($mappedFixed)->sortBy('start_time')->values();

                return response()->json([
                    'success' => true,
                    'is_sports_rental' => true,
                    'reservations' => $unified
                ]);
            }

            $orders = Order::where('seller_id', $sellerId)
                ->where('status', Order::RESERVED)
                ->with(['client', 'details.product', 'details.dish'])
                ->orderBy('updated_at', 'desc')
                ->get();

            // Reparar órdenes con precios corruptos en 0 para platos de restaurante
            foreach ($orders as $order) {
                $needsUpdate = false;
                foreach ($order->details as $detail) {
                    if ((float)$detail->price === 0.0 && $detail->dish_id && $detail->dish) {
                        $detail->price = (float)$detail->dish->designated_price;
                        $detail->save();
                        $needsUpdate = true;
                    }
                }
                if ($needsUpdate) {
                    $order->updateTotals();
                }
            }

            return response()->json([
                'success' => true,
                'is_sports_rental' => false,
                'orders' => $orders->fresh(['client', 'details.product', 'details.dish'])
            ]);
        } catch (\Exception $e) {
            Log::error('Error en getReservedOrders', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('Ocurrió un error al obtener los pedidos pendientes.', 500);
        }
    }

    public function activateOrder(Order $order): JsonResponse
    {
        try {
            $sellerId = Auth::id();
            if ($order->seller_id !== $sellerId) {
                return ApiResponse::error('No tienes permiso sobre esta orden.', 403);
            }

            // Cambiar cualquier orden PENDING activa que tenga el vendedor a RESERVED o eliminarla si está vacía
            $activePending = Order::where('seller_id', $sellerId)
                ->where('status', Order::PENDING)
                ->withCount('details')
                ->first();

            if ($activePending) {
                if ($activePending->details_count > 0) {
                    $activePending->status = Order::RESERVED;
                    $activePending->save();
                } else {
                    $activePending->delete();
                }
            }

            $order->status = Order::PENDING;
            $order->save();

            return response()->json([
                'success' => true,
                'message' => 'Orden activada correctamente.',
                'order' => $order->load(['client', 'details.product', 'details.dish'])
            ]);
        } catch (\Exception $e) {
            Log::error('Error al activar orden', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ApiResponse::error('No se pudo activar la orden.', 500);
        }
    }

    /**
     * Si el usuario es vendedor (role_id 3), fuerza el filtro seller_id al usuario actual.
     */
    private function applySellerFilterForVendedor(Request $request): void
    {
        $user = Auth::user();
        if ($user && (int) $user->role_id === 3) {
            $request->merge(['seller_id' => $user->id]);
        }
    }
}
