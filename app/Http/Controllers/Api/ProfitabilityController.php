<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Profitability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finances\StoreGlobalProfitabilityRequest;
use App\Http\Requests\Finances\ToggleProfitabilityLockRequest;
use App\Http\Requests\Finances\UpsertCategoryProfitabilityRequest;
use App\Http\Requests\ProfitabilityProductCreateRequest;
use App\Http\Requests\ProfitabilityProductEditRequest;
use App\Http\Resources\Finances\CategoryProfitabilityResource;
use App\Http\Resources\Finances\ProductProfitabilityResource;
use App\Models\Product;
use App\Models\ProductProfitability;
use App\Services\Products\ProductQueryService;
use App\Services\ProfitabilityServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfitabilityController extends Controller
{
    public function __construct(
        protected Profitability $profitability,
        protected ProductQueryService $productQueryService,
        protected ProfitabilityServices $profitabilityServices
    ) {}

    public function getProductsForProfitability(Request $request)
    {
        $query = $this->productQueryService->getFilteredQueryForProfitability($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();

            return response()->json([
                'data' => ProductProfitabilityResource::collection($items),
                'total' => $items->count(),
            ]);
        }

        $paginatedResult = $query->paginate($perPage);

        return response()->json([
            'data' => ProductProfitabilityResource::collection($paginatedResult->items()),
            'total' => $paginatedResult->total(),
        ]);
    }

    public function toggleLock(ToggleProfitabilityLockRequest $request)
    {
        $productId = $request->input('product_id');
        $percentage = $request->input('profitability_percentage', 0);

        $productProfitability = ProductProfitability::where('product_id', $productId)->first();

        if ($productProfitability) {
            $newLock = $productProfitability->is_locked == 1 ? 0 : 1;
            $productProfitability->update([
                'is_locked' => $newLock,
            ]);
        } else {
            $newLock = 1;
            ProductProfitability::create([
                'product_id' => $productId,
                'profitability_percentage' => $percentage,
                'is_locked' => 1,
            ]);
        }

        $product = Product::with(['laboratory:id,name', 'category:id,name', 'category.profitability', 'profitability'])->find($productId);

        // Si se desbloquea el producto, recalcular su precio según la cascada (Categoría -> Global)
        if ($product && $newLock === 0) {
            $product->sale_price = $this->profitabilityServices->calculateSalePriceForProduct($product);
            $product->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Bloqueo de margen actualizado con éxito',
            'data' => new ProductProfitabilityResource($product),
        ]);
    }

    public function storeProfitabilityProduct(ProfitabilityProductCreateRequest $request)
    {
        $this->profitability->storeProduct($request->profitability->all());
        $productId = $request->profitability->product_id;
        $product = Product::with(['laboratory:id,name', 'category:id,name', 'category.profitability', 'profitability'])->find($productId);

        return response()->json([
            'message' => 'Se ha guardado la rentabilidad del producto',
            'data' => new ProductProfitabilityResource($product),
        ]);
    }

    public function editProfitabilityProduct(ProfitabilityProductEditRequest $request)
    {
        $this->profitability->editProduct($request->profitability->all());
        $productId = $request->profitability->product_id;
        $product = Product::with(['laboratory:id,name', 'category:id,name', 'category.profitability', 'profitability'])->find($productId);

        return response()->json([
            'message' => 'Se ha actualizado el porcentaje',
            'data' => new ProductProfitabilityResource($product),
        ]);
    }

    public function store(StoreGlobalProfitabilityRequest $request)
    {
        $this->profitability->store($request->validated());

        return response()->json('Se ha actualizado el porcentaje');
    }

    public function getCategoriesProfitability(): JsonResponse
    {
        $categories = $this->profitability->getCategoriesWithProfitability();

        return response()->json([
            'data' => CategoryProfitabilityResource::collection($categories),
        ]);
    }

    public function upsertCategoryProfitability(UpsertCategoryProfitabilityRequest $request): JsonResponse
    {
        $categoryProfitability = $this->profitability->upsertCategoryProfitability($request->validated());

        return response()->json([
            'message' => 'Regla de rentabilidad por categoría guardada y aplicada.',
            'data' => new CategoryProfitabilityResource($categoryProfitability->category->load('profitability')),
        ]);
    }

    public function deleteCategoryProfitability(int $categoryId): JsonResponse
    {
        $this->profitability->deleteCategoryProfitability($categoryId);

        return response()->json([
            'message' => 'Regla de categoría eliminada. Los productos no bloqueados volvieron a la configuración global.',
        ]);
    }

    public function consultOne(Request $request)
    {
        return $this->profitability->consultOne();
    }

    public function getProduct($id)
    {
        if ($this->profitability->consultById($id)) {
            return new JsonResponse(['message' => 'Todo bien'], 200);
        }

        return new JsonResponse(['message' => 'Todo bien'], 404);
    }
}
