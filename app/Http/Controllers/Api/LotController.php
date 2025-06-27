<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Supplier;
use DB;
use Illuminate\Http\Request;

class LotController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductLot::query()
            ->select('product_lots.*')
            ->with('product', 'supplier')
            ->whereHas('product', function ($q) {
                $q->whereColumn('stock', '=', 'quantity');
            });    

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
            'lot_number' => 'required|string|max:255',
            'expiration_date' => 'required|date',
            'quantity' => 'required|integer|min:0',
            'cost_price' => 'required|numeric|min:0',
        ]);

        if ($request->has('stock')) {
            $productLot->product->update([
                'stock' => $request->stock,
            ]);
        }

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
            'cost_price' => 'nullable|numeric|min:0',
            'location' => 'nullable|string|max:255',
            'supplier_id' => 'nullable|exists:suppliers,id',
        ]);

        $lot = ProductLot::create($validatedData);

        // **Actualizar stock en `Product`**
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
        $suppliers = Supplier::select('id', 'supplier_name')->get();

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
}
