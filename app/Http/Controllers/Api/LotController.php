<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Supplier;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LotController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductLot::query()
            ->select('product_lots.*')
            ->with('product', 'supplier');

        if ($request->has('search')) {
            $query->where('lot_number', 'like', "%{$request->search}%");
        }

        if ($request->has('sortBy') && $request->has('orderBy')) {
            if ($request->sortBy === 'product.name') {
                $query->join('products', 'product_lots.product_id', '=', 'products.id')
                    ->orderBy('products.name', $request->orderBy);
            } elseif ($request->sortBy === 'supplier.supplier_name') {
                $query->join('suppliers', 'product_lots.supplier_id', '=', 'suppliers.id')
                    ->orderBy('suppliers.supplier_name', $request->orderBy);
            } else {
                $query->orderBy($request->sortBy, $request->orderBy);
            }
        }

        return response()->json([
            'data' => $query->paginate(10),
        ]);
    }

    public function update(Request $request, ProductLot $productLot)
    {
        $validatedData = $request->validate([
            // 'lot_number' => 'required|string|max:255',
            // 'expiration_date' => 'required|date',
            'quantity' => 'required|integer|min:0',
            // 'unit_cost' => 'required|numeric|min:0'
        ]);

        // if ($request->has('stock')) {
        //     $productLot->product->update([
        //         'stock' => $request->stock,
        //     ]);
        // }

        $productLot->update($validatedData);

        return response()->json([
            'message' => 'Lote actualizado correctamente',
            'data' => $productLot,
        ]);
    }

    public function productsWithInconsistentStock(Request $request)
    {
        $query = ProductLot::query()
            ->select('product_lots.*')
            ->with('product')
            ->whereHas('product', function ($q) {
                $q->whereColumn('stock', '!=', 'quantity');
            });

        if ($request->has('search')) {
            $query->where('lot_number', 'like', "%{$request->search}%");
        }

        if ($request->has('sortBy') && $request->has('orderBy')) {
            if ($request->sortBy === 'product.name') {
                $query->join('products', 'product_lots.product_id', '=', 'products.id')
                    ->orderBy('products.name', $request->orderBy);
            } elseif ($request->sortBy === 'product.stock') {
                $query->join('products', 'product_lots.product_id', '=', 'products.id')
                    ->orderBy('products.stock', $request->orderBy);
            } else {
                $query->orderBy($request->sortBy, $request->orderBy);
            }
        }

        return response()->json([
            'data' => $query->paginate(10),
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:0',
            'expiration_date' => 'required|date',
            'lot_number' => 'nullable|string|max:255',
            'unit_cost' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:255',
            'supplier_id' => 'nullable|exists:suppliers,id',
        ]);

        $lot = ProductLot::create($validatedData);

        $lot->product->update([
            'stock' => $validatedData['quantity'],
        ]);

        return response()->json([
            'message' => 'Lote creado correctamente',
            'data' => $lot,
        ]);
    }

    public function productsWithoutLot()
    {
        $productsWithoutLot = Product::whereDoesntHave('lots')->get();

        return response()->json([
            'data' => $productsWithoutLot,
        ]);
    }

    public function availableSuppliers()
    {
        $suppliers = Supplier::select('id', 'name')->get();

        return response()->json([
            'data' => $suppliers,
        ]);
    }

    public function destroy(ProductLot $productLot)
    {
        $productLot->delete();

        return response()->json([
            'message' => 'Lote eliminado correctamente',
        ]);
    }
    public function batchUpdate(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'lots' => 'required|array',
        ]);

        $productId = $request->product_id;
        $lotsData = $request->lots;
        $errors = [];
        $totalStock = 0;

        DB::beginTransaction();

        try {
            foreach ($lotsData as $index => $lotData) {
                $isNew = !isset($lotData['id']) || $lotData['id'] <= 0;

                $rules = $isNew ? [
                    'lot_number' => 'required|string|max:255',
                    'quantity' => 'required|integer|min:0',
                    'expiration_date' => 'required|date',
                    'unit_cost' => 'nullable|numeric|min:0',
                ] : [
                    'lot_number' => 'required|string|max:255',
                    'expiration_date' => 'required|date',
                    'quantity' => 'required|integer|min:0',
                    'unit_cost' => 'required|numeric|min:0',
                ];

                $validator = Validator::make($lotData, $rules);

                if ($validator->fails()) {
                    $errors["lote_{$index}"] = $validator->errors();
                    continue;
                }

                $validatedData = $validator->validated();

                if ($isNew) {
                    ProductLot::create([
                        'product_id' => $productId,
                        'lot_number' => $validatedData['lot_number'],
                        'quantity' => $validatedData['quantity'],
                        'expiration_date' => $validatedData['expiration_date'],
                        'unit_cost' => $validatedData['unit_cost'] ?? null,
                    ]);
                } else {
                    $productLot = ProductLot::find($lotData['id']);
                    if ($productLot) {
                        $productLot->update($validatedData);
                    }
                }

                $totalStock += $validatedData['quantity'];
            }

            if (!empty($errors)) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Algunos lotes tienen errores de validación.',
                    'errors' => $errors,
                ], 422);
            }

            $product = Product::find($productId);
            if ($product) {
                $product->update(['stock' => $totalStock]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Lotes procesados correctamente.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Ocurrió un error inesperado al procesar los lotes.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
