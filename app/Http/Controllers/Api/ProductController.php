<?php

namespace App\Http\Controllers\Api;

use App\Exports\ProductsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Product;
use App\Services\Products\ProductActionService;
use App\Services\Products\ProductQueryService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function __construct(
        private ProductQueryService $productQueryService,
        private ProductActionService $productActionService
    ) {
    }

    public function index(Request $request)
    {
        $query = $this->productQueryService->getFilteredQuery($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }

    public function store(StoreProductRequest $request)
    {
        $product = $this->productActionService->createProduct($request->validated());

        return response()->json([
            'message' => 'Producto creado con éxito.',
            'product' => $product
        ], 201);
    }

    public function updateProducts(UpdateProductRequest $request, Product $product)
    {
        $updatedProduct = $this->productActionService->updateProduct($product, $request->validated());

        return response()->json([
            'message' => 'Producto actualizado con éxito.',
            'product' => $updatedProduct
        ], 200);
    }

    public function destroy(Product $product)
    {
        $this->productActionService->deleteProduct($product);
        return response()->noContent();
    }

    public function unassignProductFromGroup(Product $product)
    {
        $wasUnassigned = $this->productActionService->unassignFromGroup($product);

        if (!$wasUnassigned) {
            return response()->json(['message' => 'Este producto no está asignado a ningún grupo.'], 400);
        }

        return response()->noContent();
    }

    public function export(Request $request)
    {
        $query = $this->productQueryService->getFilteredQuery($request);
        $format = $request->input('format', 'xlsx');
        $fileName = 'productos-' . now()->format('Y-m-d') . '.' . $format;
        return Excel::download(new ProductsExport($query), $fileName);
    }

    public function getProductAll(Request $request)
    {
        $query = Product::query();
        if ($request->filled('sortBy') && $request->filled('orderBy')) {
            $query->orderBy($request->sortBy, $request->orderBy);
        } else {
            $query->orderBy('name', 'asc');
        }
        $perPage = $request->input('itemsPerPage', 10);
        $paginatedResult = $query->paginate($perPage);
        return response()->json(['data' => $paginatedResult->items(), 'total' => $paginatedResult->total()]);
    }
    public function searchByBarcode(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string'
        ]);
        $query = $this->productQueryService->searchBarcodeProduct($request);
        if ($query) {
            return response()->json([
                'data' => $query,
                'message' => 'Producto encontrado'
            ]);
        }
        return response()->json([
            'data' => null,
            'message' => 'Producto no encontrado'
        ]);
    }
}
