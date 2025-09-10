<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReturnEntry;
use Illuminate\Http\Request;
use App\Services\Returns\ReturnsActionService;
use App\Services\Returns\ReturnsQueryService;
use App\Helpers\ApiResponse;
use Illuminate\Support\Facades\Log;

class ReturnsController extends Controller
{

      public function __construct(
        private ReturnsActionService $returnsActionService,
        private ReturnsQueryService $returnsQueryService,
    ) {}

        public function index(Request $request)
    {

        $query = $this->returnsQueryService->getQueryOrder($request);
       $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

     public function searchOrders(Request $request)
    {
           try {
              $ordersQuery = $this->returnsActionService->searchOrdersReturns(
                  $request->input('identification'), 
                  $request->all()
              );

              $perPage = $request->input('itemsPerPage', 10);
         
              if ($perPage < 0) {
                  $items = $ordersQuery->get();
                  return response()->json(['data' => $items, 'total' => $items->count()]);
              }
         
              $paginatedResult = $ordersQuery->paginate($perPage);
         
              return response()->json([
                  'data' => $paginatedResult->items(),
                  'total' => $paginatedResult->total()
              ]);
          } catch (\Exception $e) {
              return response()->json(['error' => $e->getMessage()], 404);
          }
    }

      public function returnsProduct(Request $request)
    {
        try {
            $result = $this->returnsActionService->productReturn($request);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
      public function approvedReturn($returnEntryId)
    {
       
        $ReturnEntry = ReturnEntry::findOrFail($returnEntryId);
      
        if ($ReturnEntry->status !== ReturnEntry::CREATED) {
            return response()->json(['error' => 'Solo se pueden aprobar devoluciones creadas.'], 400);
        }
        try {
            $approvedReturn = $this->returnsActionService->approvedReturn($ReturnEntry);
            return ApiResponse::success('Devolución aprobada exitosamente.', ['return' => $approvedReturn]);
        } catch (\Exception $e) {
            Log::error('Error al aprobar la devolución:', ['error' => $e->getMessage(), 'returnEntry_id' => $ReturnEntry->id]);
            return ApiResponse::error('No se pudo aprobar la devolución: ' . $e->getMessage(), 500);
        }

    }
}

