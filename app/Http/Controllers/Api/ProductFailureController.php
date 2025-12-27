<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ProductFailure;

class ProductFailureController extends Controller
{
   public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        ProductFailure::create([
            'product_id' => $validated['product_id'],
            'user_id' => Auth::id(), 
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Reporte de falla guardado correctamente.'
        ], 201);
    }
}
