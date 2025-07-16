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

        return response()->json([
            'data' => $query->paginate(10),
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

        return response()->json([
            'data' => $query->paginate(10),
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
}
