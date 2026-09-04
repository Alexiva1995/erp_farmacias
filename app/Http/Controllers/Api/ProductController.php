<?php

namespace App\Http\Controllers\Api;

use App\Exports\ProductsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateIncompleteProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Requests\Product\UpdateProductGroupRequest;
use App\Http\Requests\Product\BulkActionsProductRequest;
use App\Http\Requests\Product\SearchBarcodeProductRequest;
use App\Http\Requests\Product\MergeProductsRequest;
use App\Models\Product;
use App\Services\Products\ProductActionService;
use App\Services\Products\ProductQueryService;
use App\Services\Products\ProductStatsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function __construct(
        private ProductQueryService $productQueryService,
        private ProductActionService $productActionService,
        private ProductStatsService $productStatsService
    ) {
    }

    public function index(Request $request)
    {
        // Búsqueda directa por ID: evita filtros (q, hasStock, is_deleted, etc.)
        // Acepta product_id (snake_case) y productId (camelCase)
        $productId = $request->input('product_id') ?? $request->input('productId') ?? $request->input('id');
        if ($productId && is_numeric($productId)) {
            $product = Product::with(['category', 'laboratory', 'origin', 'group', 'profitability', 'lots', 'productSuppliers', 'variants'])
                ->find((int) $productId);
            if ($product) {
                $product->stock_calculado = $product->lots->sum('quantity');
                return response()->json(['data' => [new \App\Http\Resources\ProductResource($product)], 'total' => 1]);
            }
            return response()->json(['data' => [], 'total' => 0]);
        }

        $query = $this->productQueryService->getFilteredQuery($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => \App\Http\Resources\ProductResource::collection($items), 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json([
            'data' => \App\Http\Resources\ProductResource::collection($paginatedResult->items()),
            'total' => $paginatedResult->total()
        ]);
    }

    public function incomplete(Request $request)
    {
        $query = $this->productQueryService->getIncompleteProductsQuery($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json([
                'data' => \App\Http\Resources\IncompleteProductResource::collection($items),
                'total' => $items->count(),
            ]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json([
            'data' => \App\Http\Resources\IncompleteProductResource::collection($paginatedResult->items()),
            'total' => $paginatedResult->total(),
        ]);
    }

    public function withoutGroup(Request $request)
    {
        $query = $this->productQueryService->getProductsWithoutGroupQuery($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json([
                'data' => \App\Http\Resources\ProductResource::collection($items),
                'total' => $items->count(),
            ]);
        }
        $paginatedResult = $query->paginate($perPage);
        return response()->json([
            'data' => \App\Http\Resources\ProductResource::collection($paginatedResult->items()),
            'total' => $paginatedResult->total(),
        ]);
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

    public function updateIncomplete(UpdateIncompleteProductRequest $request, Product $product)
    {
        $validated = $request->validated();

        $this->productActionService->updateIncompleteFields($product, $validated);

        return response()->json([
            'message' => 'Producto actualizado con éxito.',
        ], 200);
    }

    public function updateProductGroup(UpdateProductGroupRequest $request, Product $product)
    {
        $this->productActionService->updateProductGroup($product, $request->integer('group_id'));

        return response()->json([
            'message' => 'Grupo asignado con éxito.',
        ], 200);
    }

    public function bulkActions(BulkActionsProductRequest $request): JsonResponse
    {
        $ids = $request->input('ids');
        $action = $request->input('action');
        $value = $request->input('value');

        switch ($action) {
            case 'delete':
                foreach ($ids as $id) {
                    $product = Product::find($id);
                    if ($product) {
                        $this->productActionService->deleteProduct($product);
                    }
                }
                $message = 'Productos eliminados en lote correctamente.';
                break;
            case 'change-category':
                Product::whereIn('id', $ids)->update(['category_id' => $value]);
                $message = 'Categoría de productos actualizada en lote correctamente.';
                break;
            case 'change-laboratory':
                Product::whereIn('id', $ids)->update(['laboratory_id' => $value]);
                $message = 'Marca/Laboratorio de productos actualizada en lote correctamente.';
                break;
            case 'toggle-active':
                $productsToToggle = Product::whereIn('id', $ids)->get();
                foreach ($productsToToggle as $p) {
                    $p->update(['is_active' => !$p->is_active]);
                }
                $message = 'Estado de productos alternado correctamente.';
                break;
            default:
                return response()->json(['message' => 'Acción masiva no soportada.'], 400);
        }

        return response()->json(['message' => $message]);
    }

    public function toggleActive(Product $product): JsonResponse
    {
        $product->update(['is_active' => !$product->is_active]);
        return response()->json([
            'message' => 'Estado del producto actualizado con éxito.',
            'is_active' => $product->is_active
        ]);
    }

    public function destroy(Product $product)
    {
        $this->productActionService->deleteProduct($product);
        return response()->noContent();
    }

    public function restore(int $id)
    {
        $product = $this->productActionService->restoreProduct($id);
        return response()->json([
            'message' => 'Producto restaurado con éxito.',
            'product' => $product
        ]);
    }

    public function toggleScarce(Product $product): JsonResponse
    {
        $updatedProduct = $this->productActionService->toggleScarceProduct($product);
        return response()->json([
            'message' => 'Estado de escasez actualizado con éxito.',
            'is_scarce' => $updatedProduct->is_scarce
        ]);
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
        // Aumentar límites significativamente para reportes grandes
        ini_set('memory_limit', '1024M');
        set_time_limit(600);

        $query = $this->productQueryService->getFilteredQuery($request);
        $format = $request->input('format', 'xlsx');

        // Optimizar consulta al máximo: solo las columnas necesarias
        $query->select(['products.id', 'products.name', 'products.laboratory_id', 'products.sale_price'])
              ->withSum('lots as stock_calculado', 'quantity')
              ->with('laboratory:id,name')
              ->without(['lots', 'origin', 'category', 'group', 'profitability']);

        if ($format === 'pdf') {
            // Obtener datos
            $products = $query->get();
            
            // Usar DomPDF con opciones de optimización
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.products-pdf', compact('products'))
                ->setPaper('a4', 'landscape')
                ->setOption('isPhpEnabled', false) // Deshabilitar PHP interno para ahorrar RAM
                ->setOption('isRemoteEnabled', false); // Deshabilitar imágenes remotas
                
            return $pdf->download('productos-' . now()->format('Y-m-d') . '.pdf');
        }

        $fileName = 'productos-' . now()->format('Y-m-d') . '.' . $format;
        return Excel::download(new ProductsExport($query), $fileName);
    }

    public function getStock(Product $product): JsonResponse
    {
        return response()->json([
            'stock' => (int) $product->lots()->sum('quantity')
        ]);
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
    public function searchByBarcode(SearchBarcodeProductRequest $request)
    {
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
    /*******************************************************************************/

    public function getProducts(): JsonResponse
    {
        try {
            // Consulta base con relaciones esenciales
            $products = Product::where('no_pvp', '!=', 1)
                ->with(['laboratory', 'category', 'lots'])
                ->withSum('lots as stock_calculado', 'quantity')
                ->orderBy('name', 'asc')   // Orden alfabético por defecto
                ->get();

            // Formatear la respuesta
            $formattedProducts = $products->map(function ($product) {
                return $this->formatProductResponse($product);
            });

            return response()->json([
                'success' => true,
                'data' => $formattedProducts,
                'total' => $products->count(),
                'message' => 'Productos obtenidos exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los productos: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Formatear la respuesta del producto con información completa
     */
    private function formatProductResponse(Product $product): array
    {
        // Obtener lotes disponibles (no expirados y con stock)
        $availableLots = $product->lots()
            ->where('quantity', '>', 0)
            ->orderBy('expiration_date')
            ->get();

        $nextExpiringLot = $availableLots->first();
        $availableStock = $availableLots->sum('quantity');

        return [
            // Información básica del producto
            'id' => $product->id,
            'name' => $product->name,
            'active_ingredient' => $product->active_ingredient,
            'presentation' => $product->presentation,
            'unit_of_measure' => $product->unit_of_measure,
            'formatted_details' => $product->formatted_details,

            // Stock y precios
            'stock' => $product->stock_calculado ?? 0,
            'available_stock' => $availableStock,
            'sale_price' => (float) $product->sale_price,
            'unit_cost' => (float) $product->unit_cost,
            'sale_price_cop' => (float) $product->price_cop,
            'unit_cost_cop' => (float) (ceil($product->unit_cost * app(\App\Services\Resources\ResourceService::class)->getExchangeRate('COP') / 100) * 100),
            'barcode' => $product->barcode,

            // Información de expiración
            'next_expiration' => $nextExpiringLot ? $nextExpiringLot->expiration_date->format('Y-m-d') : null,
            'expiration_status' => $this->getExpirationStatus($nextExpiringLot),
            'has_expiring_lots' => $availableLots->where('is_expiring_soon', true)->isNotEmpty(),

            // Lotes disponibles
            'lots' => $availableLots->map(function ($lot) {
                return [
                    'id' => $lot->id,
                    'lot_number' => $lot->lot_number,
                    'expiration_date' => $lot->expiration_date->format('Y-m-d'),
                    'quantity' => $lot->quantity,
                    'location' => $lot->location,
                    'is_expiring_soon' => $lot->is_expiring_soon,
                    'months_to_expiration' => $lot->months_to_expiration,
                    'expiration_status' => $this->getLotExpirationStatus($lot),
                ];
            })->values(),

            // Relaciones
            'laboratory' => $product->laboratory ? [
                'id' => $product->laboratory->id,
                'name' => $product->laboratory->name,
            ] : null,

            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
            ] : null,

            // URLs e imágenes
            'photo_url' => $product->photo_url,

            // Flags y estados
            'psychotropic' => (bool) $product->psychotropic,
            'is_active' => (bool) $product->is_active,
            'has_stock' => $availableStock > 0,
            'is_available' => $product->is_active && $availableStock > 0,

            // Timestamps
            'created_at' => $product->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $product->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Determinar el estado de expiración del producto
     */
    private function getExpirationStatus($lot): string
    {
        if (!$lot) {
            return 'no-expiration';
        }

        $monthsToExpire = $lot->months_to_expiration;

        if ($monthsToExpire <= 1) {
            return 'expiring-soon';
        } elseif ($monthsToExpire <= 3) {
            return 'expiring';
        } else {
            return 'good';
        }
    }

    /**
     * Determinar el estado de expiración de un lote específico
     */
    private function getLotExpirationStatus($lot): string
    {
        if (!$lot) {
            return 'no-expiration';
        }

        $expirationDate = $lot->expiration_date;

        // Si ya expiró
        if ($expirationDate->isPast()) {
            return 'expired';
        }

        $monthsToExpire = $expirationDate->diffInMonths(now());

        if ($monthsToExpire <= 1) {
            return 'expiring-soon';
        } elseif ($monthsToExpire <= 3) {
            return 'expiring';
        } else {
            return 'good';
        }
    }

    /**
     * Obtener productos para autocomplete (para usar en packs)
     * También sin parámetros
     */
    public function forAutocomplete(): JsonResponse
    {
        try {
            $q = request()->query('q');
            
            // Buscar productos ordenados por nombre
            $query = Product::with(['lots', 'laboratory']);

            // Si se proporciona un término de búsqueda, filtrar
            if (!empty($q)) {
                $query->where(function ($queryBuilder) use ($q) {
                    $queryBuilder->where('name', 'like', "%{$q}%")
                        ->orWhere('active_ingredient', 'like', "%{$q}%")
                        ->orWhere('barcode', 'like', "%{$q}%");
                });
            }

            $products = $query->orderBy('name', 'asc')
                ->limit(50) // Límite razonable para autocomplete
                ->get();

            // Buscar platos base si coincide con la búsqueda
            $baseDishesQuery = \App\Models\Dish::with('ingredients')
                ->where('status', 3);

            if (!empty($q)) {
                $baseDishesQuery->where('name', 'like', "%{$q}%");
            }

            $baseDishes = $baseDishesQuery->limit(10)->get();

            $formattedProducts = $products->map(function ($product) {
                $activeLots = $product->lots->filter(fn($lot) => $lot->quantity > 0);

                $nextLot = $activeLots
                    ->sortBy('expiration_date')
                    ->first();

                return [
                    'id' => $product->id,
                    'is_base_dish' => false,
                    'name' => $product->name,
                    'active_ingredient' => $product->active_ingredient,
                    'presentation' => $product->presentation ?: 1,
                    'unit_of_measure' => $product->unit_of_measure ?: 'und',
                    'stock' => $product->stock,
                    'available_stock' => $activeLots
                        ->sum('quantity'),
                    'sale_price' => (float) $product->sale_price,
                    'unit_cost' => (float) $product->unit_cost,
                    'next_expiration' => $nextLot ? $nextLot->expiration_date->format('Y-m-d') : null,
                    'expiration_status' => $this->getExpirationStatus($nextLot),
                    'laboratory' => $product->laboratory ? $product->laboratory->name : null,
                    'formatted_details' => $product->formatted_details,
                    'has_stock' => $product->stock > 0,
                    'is_available' => true,
                    'lots' => $activeLots
                        ->sortBy('expiration_date')
                        ->values()
                        ->map(function ($lot) {
                            return [
                                'id' => $lot->id,
                                'lot_number' => $lot->lot_number,
                                'expiration_date' => $lot->expiration_date->format('Y-m-d'),
                                'quantity' => $lot->quantity,
                                'is_expiring_soon' => $lot->is_expiring_soon,
                                'expiration_status' => $this->getLotExpirationStatus($lot),
                            ];
                        }),
                ];
            });

            // Combinar los platos base formateados
            $formattedDishes = $baseDishes->map(function ($dish) {
                return [
                    'id' => 'dish_' . $dish->id,
                    'is_base_dish' => true,
                    'name' => '[RECETA BASE] ' . $dish->name,
                    'active_ingredient' => null,
                    'presentation' => 1,
                    'unit_of_measure' => 'receta',
                    'stock' => 0,
                    'available_stock' => 0,
                    'sale_price' => (float) $dish->designated_price,
                    'unit_cost' => (float) $dish->cost_price,
                    'next_expiration' => null,
                    'expiration_status' => 'good',
                    'laboratory' => null,
                    'formatted_details' => 'Importar ingredientes de esta receta base',
                    'has_stock' => true,
                    'is_available' => true,
                    'lots' => [],
                    'ingredients' => $dish->ingredients->map(function ($ing) {
                        return [
                            'product_id' => $ing->id,
                            'name' => $ing->name,
                            'portion' => (float) $ing->pivot->portion,
                            'unit_cost' => (float) $ing->unit_cost,
                            'presentation' => (float) $ing->presentation,
                            'unit_of_measure' => $ing->unit_of_measure,
                            'designated_cost' => (float) $ing->pivot->designated_cost,
                        ];
                    })->values()->toArray(),
                ];
            });

            $mergedResult = $formattedProducts->concat($formattedDishes);

            return response()->json([
                'success' => true,
                'data' => $mergedResult,
                'total' => $mergedResult->count(),
                'message' => 'Productos para autocomplete obtenidos exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener productos para autocomplete: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Obtener productos próximos a expirar (sin parámetros)
     */
    public function expiringSoon(): JsonResponse
    {
        try {
            $products = Product::with(['lots', 'laboratory'])
                ->whereHas('lots', function ($query) {
                    $query->where('quantity', '>', 0)
                        ->where('expiration_date', '<=', now()->addMonths(3))
                        ->where('expiration_date', '>', now());
                })
                ->orderBy('name', 'asc')
                ->get();

            $formattedProducts = $products->map(function ($product) {
                return $this->formatProductResponse($product);
            });

            return response()->json([
                'success' => true,
                'data' => $formattedProducts,
                'total' => $products->count(),
                'message' => 'Productos próximos a expirar obtenidos exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener productos próximos a expirar: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    public function getInventoryValue(Request $request)
    {
        $inventoryValue = $this->productQueryService->calculateInventoryValue();

        return response()->json([
            'data' => [
                'total_value' => $inventoryValue,
                'currency' => 'USD',
                'calculated_at' => now()->toISOString()
            ],
            'message' => 'Valor del inventario calculado con éxito.'
        ], 200);
    }

    public function merge(MergeProductsRequest $request)
    {
        try {
            $result = $this->productActionService->mergeProducts(
                $request->integer('product_id_1'),
                $request->integer('product_id_2'),
                $request->integer('keep_product_id')
            );

            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de ventas del producto.
     */
    public function getStats(Product $product): JsonResponse
    {
        try {
            $stats = $this->productStatsService->getProductSalesStats($product);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Estadísticas obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el número correlativo siguiente para el lote de un producto.
     */
    public function getNextLotNumber(Product $product): JsonResponse
    {
        try {
            // Buscar el último número de lote del producto que sea numérico
            $lastLot = \App\Models\ProductLot::where('product_id', $product->id)
                ->whereRaw("lot_number REGEXP '^[0-9]+$'")
                ->orderByRaw('CAST(lot_number AS UNSIGNED) DESC')
                ->first();

            $nextNumber = 1;
            if ($lastLot) {
                $nextNumber = ((int) $lastLot->lot_number) + 1;
            } else {
                // Si no hay lotes puramente numéricos, buscar el último lote creado de cualquier tipo y tratar de extraer o usar 1
                $lastLotAny = \App\Models\ProductLot::where('product_id', $product->id)
                    ->orderBy('id', 'desc')
                    ->first();
                if ($lastLotAny && is_numeric($lastLotAny->lot_number)) {
                    $nextNumber = ((int) $lastLotAny->lot_number) + 1;
                }
            }

            return response()->json([
                'success' => true,
                'next_lot_number' => (string) $nextNumber,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular el siguiente número de lote: ' . $e->getMessage()
            ], 500);
        }
    }
}

