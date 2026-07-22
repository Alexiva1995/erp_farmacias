<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Profitability;
use App\Models\ProfitabilitySettings;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfitabilityProductCreateRequest;
use App\Http\Requests\ProfitabilityProductEditRequest;
use App\Models\Product;
use App\Models\ProductProfitability;
use App\Services\Products\ProductQueryService;
use App\Http\Resources\Finances\ProductProfitabilityResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfitabilityController extends Controller
{
    public function __construct(
        protected Profitability $profitability,
        protected ProductQueryService $productQueryService
    ) {}

    public function getProductsForProfitability(Request $request)
    {
        $query = $this->productQueryService->getFilteredQueryForProfitability($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json([
                'data' => ProductProfitabilityResource::collection($items),
                'total' => $items->count()
            ]);
        }

        $paginatedResult = $query->paginate($perPage);
        return response()->json([
            'data' => ProductProfitabilityResource::collection($paginatedResult->items()),
            'total' => $paginatedResult->total()
        ]);
    }

    public function toggleLock(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'profitability_percentage' => 'nullable|numeric',
        ]);

        $productId = $request->input('product_id');
        $percentage = $request->input('profitability_percentage', 0);

        $profitability = ProductProfitability::where('product_id', $productId)->first();

        if ($profitability) {
            $newLock = $profitability->is_locked == 1 ? 0 : 1;
            $profitability->update([
                'is_locked' => $newLock,
            ]);
        } else {
            $profitability = ProductProfitability::create([
                'product_id' => $productId,
                'profitability_percentage' => $percentage,
                'is_locked' => 1,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Bloqueo de margen actualizado con éxito',
            'data' => $profitability
        ]);
    }

    public function storeProfitabilityProduct(ProfitabilityProductCreateRequest $request)
    {
        $this->profitability->storeProduct($request->profitability->all());
        return response()->json("Se ha guardado la rentabilidad del producto");
    }

    public function editProfitabilityProduct(ProfitabilityProductEditRequest $request)
    {
        $this->profitability->editProduct($request->profitability->all());
        return response()->json("Se ha actualizado el porcentaje");
    }

    public function store(Request $request)
    {
        $crear = [
            'default_profitability_percentage' => $request->default_profitability_percentage,
            'shipping_cost' => $request->shipping_cost,
            'packaging_cost' => $request->packaging_cost,
            'expense_margin' => $request->expense_margin,
            'profit_margin' => $request->profit_margin,
            'tax_usa' => $request->tax_usa,
        ];
        $this->profitability->store($crear);
        return response()->json("Se ha actualizado el porcentaje");
    }

    public function consultOne(Request $request)
    {
        return $this->profitability->consultOne();
    }

    public function getProduct($id)
    {
        if ($this->profitability->consultById($id)) {
            $data = [
                "message" => "Todo bien"
            ];
            return new JsonResponse($data, 200);
        } else {
            $data = [
                "message" => "Todo bien"
            ];
            return new JsonResponse($data, 404);
        }
    }
}
