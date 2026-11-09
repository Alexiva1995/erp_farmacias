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
use App\Services\Catalog\MasterCatalogBranchService;
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
        private ProductStatsService $productStatsService,
        private MasterCatalogBranchService $masterCatalogBranchService
    ) {
    }

    public function index(Request $request)
    {
        $isMasterContext = $request->header('X-Master-Catalog-Context') === '1'
            || $request->boolean('is_master')
            || config('database.default') === 'master_catalog';

        // Búsqueda directa por ID: evita filtros (q, hasStock, is_deleted, etc.)
        // Acepta product_id (snake_case) y productId (camelCase)
        $productId = $request->input('product_id') ?? $request->input('productId') ?? $request->input('id');
        if ($productId && is_numeric($productId)) {
            $product = Product::with(['category', 'laboratory', 'origin', 'group', 'profitability', 'lots', 'productSuppliers', 'variants'])
                ->find((int) $productId);
            if ($product) {
                $product->stock_calculado = $product->lots->sum('quantity');
                if ($isMasterContext) {
                    $this->masterCatalogBranchService->attachActiveBranches([$product]);
                }
                return response()->json(['data' => [new \App\Http\Resources\ProductResource($product)], 'total' => 1]);
            }
            return response()->json(['data' => [], 'total' => 0]);
        }

        $query = $this->productQueryService->getFilteredQuery($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            if ($isMasterContext) {
                $this->masterCatalogBranchService->attachActiveBranches($items);
            }
            return response()->json(['data' => \App\Http\Resources\ProductResource::collection($items), 'total' => $items->count()]);
        }
        $paginatedResult = $query->paginate($perPage);
        if ($isMasterContext) {
            $this->masterCatalogBranchService->attachActiveBranches($paginatedResult->items());
        }
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
        $rawStock = (float) $product->lots()->sum('quantity');

        // Para auditoría y conteos físicos, las existencias reales no pueden ser negativas.
        // Si hay saldo negativo virtual por ventas previas a orden de producción, la base física es 0.
        return response()->json([
            'stock' => max(0.0, $rawStock),
            'raw_stock' => $rawStock,
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
            \App\Models\Dish::ensureRestaurantSchema();

            $q = trim((string) request()->query('q'));
            
            // Buscar productos ordenados por nombre
            $query = Product::with(['lots', 'laboratory']);

            // Si se proporciona un término de búsqueda, filtrar
            if ($q !== '') {
                $query->where(function ($queryBuilder) use ($q) {
                    $queryBuilder->where('name', 'like', "%{$q}%")
                        ->orWhere('active_ingredient', 'like', "%{$q}%")
                        ->orWhere('barcode', 'like', "%{$q}%");
                });
            }

            $products = $query->orderBy('name', 'asc')
                ->limit(50) // Límite razonable para autocomplete
                ->get();

            $formattedProducts = $products->map(function ($product) {
                $activeLots = $product->lots->filter(fn($lot) => $lot->quantity > 0);

                $nextLot = $activeLots
                    ->sortBy('expiration_date')
                    ->first();

                $unitCost = (float) $product->unit_cost;
                $isSubRecipe = (bool) $product->is_sub_recipe;

                // Si el producto es una sub-receta o está vinculado como salida de una sub-receta, resolver su costo dinámico
                if ($unitCost <= 0 || !empty($product->is_sub_recipe)) {
                    try {
                        $baseDishQuery = \App\Models\Dish::with('ingredients');
                        if (\Illuminate\Support\Facades\Schema::hasColumn('dishes', 'output_product_id')) {
                            $baseDishQuery->where(function ($queryBuilder) use ($product, $isSubRecipe) {
                                $queryBuilder->where('output_product_id', $product->id);
                                if ($isSubRecipe) {
                                    $queryBuilder->orWhere(function ($subQ) use ($product) {
                                        $subQ->whereNull('output_product_id')
                                             ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim((string) $product->name))])
                                             ->where('status', 3);
                                    });
                                }
                            });
                        } else {
                            $baseDishQuery->whereRaw('LOWER(TRIM(name)) = ?', [strtolower(trim((string) $product->name))])
                                ->where('status', 3);
                        }

                        $baseDish = $baseDishQuery->first();
                        $dishIngredientIds = $baseDish && $baseDish->ingredients
                            ? $baseDish->ingredients->pluck('id')->map(fn ($id) => (int) $id)->toArray()
                            : [];

                        if ($baseDish && !in_array((int) $product->id, $dishIngredientIds, true)) {
                            $isSubRecipe = true;
                            $yield = (float) ($baseDish->expected_yield_quantity ?: 1.0);
                            $safeYield = $yield > 0 ? $yield : 1.0;
                            
                            $rawDishCost = 0.0;
                            if ($baseDish->ingredients && $baseDish->ingredients->isNotEmpty()) {
                                foreach ($baseDish->ingredients as $ing) {
                                    if ((int) $ing->id === (int) $product->id) {
                                        continue;
                                    }
                                    $pPortion = (float) ($ing->pivot?->portion ?? 0);
                                    $pUnitCost = (float) ($ing->unit_cost ?? 0);
                                    $pPres = (float) ($ing->presentation ?? 1);
                                    $pPresSafe = $pPres > 0 ? $pPres : 1.0;
                                    $rawDishCost += ($pUnitCost > 0)
                                        ? round(($pPortion * $pUnitCost) / $pPresSafe, 4)
                                        : (float) ($ing->pivot?->designated_cost ?? 0);
                                }
                            }
                            $dishCost = $rawDishCost > 0 ? round($rawDishCost * 1.05, 4) : (float) $baseDish->cost_price;
                            if ($dishCost > 0) {
                                $unitCost = round($dishCost / $safeYield, 4);
                                $product->updateQuietly([
                                    'unit_cost' => $unitCost,
                                    'sale_price' => $unitCost,
                                    'is_sub_recipe' => true,
                                ]);
                                if (empty($baseDish->output_product_id)) {
                                    $baseDish->updateQuietly(['output_product_id' => $product->id]);
                                }
                            }
                        } elseif ($unitCost <= 0) {
                            $lotWithCost = $activeLots->first(fn($l) => (float) $l->unit_cost > 0);
                            if ($lotWithCost) {
                                $unitCost = (float) $lotWithCost->unit_cost;
                            }
                        }
                    } catch (\Throwable $e) {}
                }

                return [
                    'id' => $product->id,
                    'is_base_dish' => false,
                    'is_sub_recipe' => $isSubRecipe,
                    'name' => $product->name,
                    'active_ingredient' => $product->active_ingredient,
                    'presentation' => $product->presentation ?: 1,
                    'unit_of_measure' => $product->unit_of_measure ?: 'und',
                    'stock' => $product->stock,
                    'available_stock' => $activeLots
                        ->sum('quantity'),
                    'sale_price' => (float) ($product->sale_price ?: $unitCost),
                    'unit_cost' => $unitCost,
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

            // Deduplicar solo si comparten el mismo nombre normalizado Y el mismo tipo (ambos insumo o ambos sub-receta),
            // manteniendo tanto el insumo base como la sub-receta si coexisten con igual nombre.
            $groupedByNameAndType = $formattedProducts->groupBy(function ($item) {
                $normName = strtolower(trim(preg_replace('/\s+/', ' ', (string) $item['name'])));
                $typeFlag = !empty($item['is_sub_recipe']) ? 'sub' : 'raw';
                return $normName . '|' . $typeFlag;
            });

            $filteredProducts = $groupedByNameAndType->map(function ($group) {
                return $group->first();
            })->values();

            return response()->json([
                'success' => true,
                'data' => $filteredProducts,
                'total' => $filteredProducts->count(),
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

