<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductLot;
use Illuminate\Http\Request;

class LotController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductLot::with('product', 'supplier');
            // ->whereHas('product', function ($q) {
            //     $q->whereColumn('stock', '=', 'quantity');
            // });

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

        $productLot->update($validatedData);

        return response()->json([
            'message' => 'Lote actualizado correctamente',
            'data' => $productLot,
        ]);
    }
}
