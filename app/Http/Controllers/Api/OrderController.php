<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Quotation\QuotationQueryService;
use App\Contracts\Client;
use App\Helpers\ApiResponse;
use App\Services\Order\OrderActionService;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{

    public function __construct(
        private QuotationQueryService $quotationQueryService,
        protected Client $client,
        private OrderActionService $orderActionService
    ) {
    }
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
           //         'has_pending_credits' => false,
                    'identification_searched' => $request->Identification
                ],"the client not found", 200);
        }

     //   $pendingCredits = $buscarPorIdentificaion->pendingCredits;
     //   $hasPendingCredits = $pendingCredits->isNotEmpty();
     //   $totalPendingAmount = $pendingCredits->sum('pending_amount');
       /*  $pendingCreditsData = $pendingCredits->map(function($credit) {
        return [
            'id' => $credit->id,
            'order_id' => $credit->order_id,
            'amount' => $credit->amount,
            'pending_amount' => $credit->pending_amount,
        ];
    })->toArray();*/

        return ApiResponse::success([
                'found' => true,
                'client' => $buscarPorIdentificaion,
         //       'has_pending_credits' => $hasPendingCredits,
                //'pending_credits_data' => $pendingCreditsData,
         //       'total_pending_amount' => $totalPendingAmount
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

    public function getMyOpenOrder(Request $request){
       // $sellerId = Auth::user()->id;
        $sellerId = 3;
        if (!$sellerId) {
            return ApiResponse::error('Vendedor no autenticado.', 401);
        }
        $openOrder = Order::with('client')
                          ->where('seller_id', $sellerId)
                          ->whereIn('status', ['Pending'])
                          ->first();

        if ($openOrder) {
            return ApiResponse::success([
                'order' => $openOrder->toArray()
            ], "Orden abierta de vendedor encontrada.", 200);
        } else {
            return ApiResponse::success([
                'order' => null
            ], "No se encontró orden abierta para el vendedor.", 200);
        }
    }
}
