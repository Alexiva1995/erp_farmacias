<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Returns\ReturnsActionService;

class ReturnsController extends Controller
{

      public function __construct(
        private ReturnsActionService $returnsActionService,
    ) {}

     public function searchOrders(Request $request)
    {
           try {
              // Cambia $request->Identification por $request->input('identification')
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

}

