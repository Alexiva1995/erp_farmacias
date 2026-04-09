<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lots\StoreLotRequest;
use App\Http\Requests\Lots\UpdateLotRequest;
use App\Http\Requests\Lots\BatchUpdateLotRequest;
use App\Models\ProductLot;
use App\Services\Lots\LotActionService;
use App\Services\Lots\LotQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LotController extends Controller
{
    public function __construct(
        private LotQueryService $lotQueryService,
        private LotActionService $lotActionService
    ) {
    }

    public function index(Request $request)
    {
        $query = $this->lotQueryService->getFilteredQuery($request);

        $perPage = (int) $request->get('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();

            return response()->json([
                'data' => [
                    'data' => $items,
                    'total' => $items->count(),
                ],
            ]);
        }

        return response()->json([
            'data' => $query->paginate($perPage),
        ]);
    }

    public function store(StoreLotRequest $request)
    {
        try {
            $lot = $this->lotActionService->createLot($request->validated());

            return response()->json([
                'message' => 'Lote creado correctamente',
                'data' => $lot,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación al crear el lote.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno del servidor al crear el lote.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateLotRequest $request, ProductLot $productLot)
    {
        try {
            $updatedLot = $this->lotActionService->updateLot($productLot, $request->validated());

            return response()->json([
                'message' => 'Lote actualizado correctamente',
                'data' => $updatedLot,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación al actualizar el lote.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno del servidor al actualizar el lote.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(ProductLot $productLot)
    {
        try {
            $this->lotActionService->deleteLot($productLot);

            return response()->json([
                'message' => 'Lote eliminado correctamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno del servidor al eliminar el lote.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function batchUpdate(BatchUpdateLotRequest $request)
    {
        try {
            $result = $this->lotActionService->batchUpdateLots($request->validated());

            if (isset($result['errors'])) {
                return response()->json([
                    'message' => 'Algunos lotes tienen errores de validación.',
                    'errors' => $result['errors'],
                ], 422);
            }

            return response()->json([
                'message' => 'Lotes procesados correctamente.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error interno del servidor al procesar los lotes.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function productsWithInconsistentStock(Request $request)
    {
        $query = $this->lotQueryService->getProductsWithInconsistentStockQuery($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json(['data' => $items, 'total' => $items->count()]);
        }
        
        $paginatedResult = $query->paginate($perPage);
        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total(),
        ]);
    }

    public function getProductsPendingLotification(Request $request)
    {
        $query = $this->lotQueryService->getProductsPendingLotificationQuery($request);
        $perPage = (int) $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            return response()->json([
                'data' => $items,
                'total' => $items->count()
            ]);
        }

        $paginatedResult = $query->paginate($perPage);
        return response()->json([
            'data' => $paginatedResult->items(),
            'total' => $paginatedResult->total(),
        ]);
    }

    public function productsWithoutLot()
    {
        $products = $this->lotQueryService->getProductsWithoutLot();

        return response()->json([
            'data' => $products,
        ]);
    }

    public function availableSuppliers()
    {
        $suppliers = $this->lotQueryService->getAvailableSuppliers();

        return response()->json([
            'data' => $suppliers,
        ]);
    }

    /**
     * Obtener todos los lotes de un producto específico
     */
    public function getProductLots(Request $request, $productId)
    {
        try {
            $product = \App\Models\Product::find($productId);
            if (!$product) {
                return response()->json([
                    'message' => 'Producto no encontrado.',
                ], 404);
            }

            $lots = ProductLot::with(['product.laboratory', 'supplier'])
                ->where('product_id', $productId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'data' => [
                    'data' => $lots,
                    'product' => $product,
                    'total' => $lots->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los lotes del producto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Método adicional para obtener información de stock disponible para un producto
     */
    public function getAvailableStock(Request $request, $productId)
    {
        try {
            $product = \App\Models\Product::find($productId);
            if (!$product) {
                return response()->json([
                    'message' => 'Producto no encontrado.',
                ], 404);
            }

            $currentLotsSum = ProductLot::where('product_id', $productId)->sum('quantity');
            $availableStock = max(0, $product->stock - $currentLotsSum);

            return response()->json([
                'data' => [
                    'product_id' => $productId,
                    'product_stock' => $product->stock,
                    'lots_sum' => $currentLotsSum,
                    'available_stock' => $availableStock,
                    'has_discrepancy' => $currentLotsSum != $product->stock,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener información de stock.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteLotsWithZeroQuantity()
    {
        try {
            $deletedCount = $this->lotActionService->deleteLotsWithZeroQuantity();

            return response()->json([
                'message' => "Se eliminaron {$deletedCount} lotes con cantidad 0.",
                'deleted_count' => $deletedCount,
            ]);
        } catch (\Exception $e) {
            $errorDetails = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];

            // Si hay una excepción previa, agregar sus detalles
            if ($e->getPrevious()) {
                $previous = $e->getPrevious();
                $errorDetails['previous'] = [
                    'message' => $previous->getMessage(),
                    'code' => $previous->getCode(),
                    'file' => $previous->getFile(),
                    'line' => $previous->getLine(),
                ];
            }

            Log::error('Error al eliminar lotes con cantidad 0', $errorDetails);

            // Construir mensaje de error más descriptivo
            $errorMessage = 'Error al eliminar los lotes con cantidad 0.';
            if (str_contains($e->getMessage(), 'foreign key constraint')) {
                $errorMessage .= ' El lote está siendo referenciado por otros registros (expirations, inventory_movements, etc.).';
            } elseif (str_contains($e->getMessage(), 'SQLSTATE')) {
                $errorMessage .= ' Error de base de datos: ' . $e->getMessage();
            } else {
                $errorMessage .= ' ' . $e->getMessage();
            }

            return response()->json([
                'message' => $errorMessage,
                'error' => $e->getMessage(),
                'details' => config('app.debug') ? $errorDetails : null,
            ], 500);
        }
    }

    public function lotsWithoutLocation(Request $request)
    {
        $query = $this->lotQueryService->getLotsWithoutLocationQuery($request);
        $perPage = $request->input('itemsPerPage', 10);

        if ($perPage < 1) {
            $items = $query->get();
            $data = $items->map(fn ($lot) => $this->mapLotWithoutLocationToProductId($lot));

            return response()->json(['data' => ['data' => $data, 'total' => $data->count()]]);
        }

        $paginatedResult = $query->paginate($perPage);
        $data = collect($paginatedResult->items())->map(fn ($lot) => $this->mapLotWithoutLocationToProductId($lot));

        return response()->json([
            'data' => ['data' => $data->values()->all(), 'total' => $paginatedResult->total()],
        ]);
    }

    /**
     * Expone id como product_id y el id del lote como lot_id para la vista lotes sin ubicación.
     */
    private function mapLotWithoutLocationToProductId(ProductLot $lot): array
    {
        $arr = $lot->toArray();
        $arr['lot_id'] = $arr['id'];
        $arr['id'] = $lot->product_id;

        return $arr;
    }
}
