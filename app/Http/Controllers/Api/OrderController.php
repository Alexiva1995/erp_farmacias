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
        $query = $this->orderQueryService->getFilteredQueryProduct($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    public function consultByIdentification(Request $request)
    {
        $buscarPorIdentificaion = $this->client->consultByIdentification($request->Identification);
        if (!$buscarPorIdentificaion) {
            return ApiResponse::success([
                'found' => false,
                'identification_searched' => $request->Identification
            ], "the client not found", 200);
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
            $sellerId = Auth::id();
            if (!$sellerId) {
                return ApiResponse::error('Vendedor no autenticado.', 401);
            }

            $openOrder = $this->orderActionService->getMyOpenOrder($sellerId);

            if ($openOrder) {
                return ApiResponse::success([
                    'order' => $openOrder,
                ], "Orden abierta de vendedor encontrada.", 200);
            } else {
                return ApiResponse::success([
                    'order' => null
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

    public function completeOrder(Order $orderId, Request $request)
    {
        if ($orderId->details()->doesntExist()) {
            return ApiResponse::error('No hay productos en la orden', 500);
        }

        try {
            $sellerId = Auth::id();
            if ($request->has('items')) {
                $request->merge(['items' => json_decode($request->items, true)]);
            }
            if ($request->has('payments')) {
                $request->merge(['payments' => json_decode($request->payments, true)]);
            }

            $result = $this->orderActionService->complete($orderId, $request, $sellerId);
            return ApiResponse::success($result, 'Compra finalizada exitosamente.', 200);

        } catch (\Exception $e) {
            Log::error('Error al completar la orden:', ['error' => $e->getMessage(), 'order_id' => $orderId->id]);
            return ApiResponse::error('No se pudo completar la orden: ' . $e->getMessage(), 500);
        }
    }

    public function getcompletedOrder(Request $request)
    {
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
        $order = Order::with('details.product', 'client', 'seller')->find($orderId);
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
        $existingReservedOrder = Order::where('seller_id', $sellerId)
            ->where('status', 'Reserved')
            ->first();

        if ($existingReservedOrder) {
            return ApiResponse::error('Ya tienes una orden reservada.', 409);
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

            $debitoFiscalData = $this->orderQueryService->getDebitoFiscal($startDate, $endDate);

            return ApiResponse::success([
                'periodo' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'debito_fiscal' => $debitoFiscalData['total_debito'],
                'detalle_debito' => [
                    'total_orders_with_iva' => $debitoFiscalData['total_records'],
                    'total_iva_amount' => $debitoFiscalData['total_iva_amount'],
                    'total_spe_amount' => $debitoFiscalData['total_spe_amount'] ?? 0,
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
                'page' => 'integer|min:1',
                'itemsPerPage' => 'integer|min:1|max:100'
            ]);

            $startDate = $request->start_date ?? now()->startOfMonth()->format('Y-m-d');
            $endDate = $request->end_date ?? now()->endOfMonth()->format('Y-m-d');
            $page = $request->page ?? 1;
            $itemsPerPage = $request->itemsPerPage ?? 10;

            $fiscalData = $this->orderQueryService->getFiscalHistoryRecords(
                $startDate,
                $endDate,
                $page,
                $itemsPerPage
            );

            return ApiResponse::success($fiscalData, 'Registros de fiscal history obtenidos exitosamente');

        } catch (\Exception $e) {
            Log::error('Error al obtener registros de fiscal history: ' . $e->getMessage());
            return ApiResponse::error('Error al obtener registros de fiscal history: ' . $e->getMessage(), 500);
        }
    }

    public function cancelledOrder(Order $order)
    {
        if ($order->status !== Order::COMPLETED) {
            return ApiResponse::error('Solo se pueden cancelar órdenes completadas.', 400);
        }
        try {
            $abandonedOrder = $this->orderActionService->cancelledOrder($order);
            return ApiResponse::success('Orden cancelada exitosamente.', ['order' => $abandonedOrder]);
        } catch (\Exception $e) {
            Log::error('Error al cancelada la orden:', ['error' => $e->getMessage(), 'order_id' => $order->id]);
            return ApiResponse::error('No se pudo cancelada la orden: ' . $e->getMessage(), 500);
        }
    }

}
