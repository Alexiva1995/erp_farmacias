<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Quotation\QuotationQueryService;
use App\Contracts\Client;
use App\Helpers\ApiResponse;
use App\Services\Order\OrderActionService;
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


class OrderController extends Controller
{

    public function __construct(
        private QuotationQueryService $quotationQueryService,
        protected Client $client,
        private OrderActionService $orderActionService
    ) {}
    public function index(Request $request)
    {
        $query = $this->quotationQueryService->getFilteredQuery($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    public function  consultByIdentification(Request $request)
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
            // $sellerId = Auth::id();
            $sellerId = 3;
            if (!$sellerId) {
                return ApiResponse::error('Vendedor no autenticado.', 401);
            }
            $openOrder = $this->orderActionService->getMyOpenOrder($sellerId);
            if ($openOrder) {
                return ApiResponse::success([
                    'order' => $openOrder->toArray()
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


    public function storeOrderItem(AddOrderItemRequest $request, Order $order){
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

    public function deleteOrderDetail(Order $order, OrderDetail $item){
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
}
