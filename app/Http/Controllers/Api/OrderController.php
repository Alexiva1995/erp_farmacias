<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Quotation\QuotationQueryService;

class OrderController extends Controller
{

      public function __construct(
        private QuotationQueryService $quotationQueryService,
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
}
