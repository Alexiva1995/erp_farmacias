<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{

    public function index(Request $request)
    {
        return 'test';
        $query = Product::with(['category', 'laboratory', 'origin']);

        // Filtrado por búsqueda (q)
        if ($request->has('q') && $request->q) {
            $query->where('name', 'like', "%{$request->q}%");
        }

        // Ordenamiento
        if ($request->has('sortBy') && $request->has('orderBy')) {
            $query->orderBy($request->sortBy, $request->orderBy);
        }

        // Paginación: VDataTableServer envía 'itemsPerPage' y 'page'
        $perPage = $request->input('itemsPerPage', 10);
        $products = $query->paginate($perPage);
        Log::info($products);
        return response()->json($products);
    }
}
