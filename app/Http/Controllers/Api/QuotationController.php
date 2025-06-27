<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Quotation\QuotationActionService;
use App\Services\Quotation\QuotationQueryService;
use App\Models\Product;

class QuotationController extends Controller
{
     public function __construct(
        private QuotationQueryService $quotationQueryService,
        private QuotationActionService  $quotationActionService
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

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        $detailedProduct = $this->quotationActionService->loadProductDetails($product);
       
        if (!$detailedProduct) {
             return response()->json([
                'status'  => 'error',
                'message' => "Producto no encontrado.",
            ]);
        }

        return response()->json($detailedProduct);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
