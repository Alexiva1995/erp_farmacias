<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ValidateBarcodeRequest;
use App\Http\Requests\Inventory\ProcessCountRequest;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class InventoryAdjustmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function validateBarcode(ValidateBarcodeRequest $request, Product $product)
    {
        $scannedBarcode = $request->input('barcode');
        if ($product->barcode === $scannedBarcode) {
            return response()->json(['message' => 'Código de barras validado correctamente.']);
        }
        Log::warning("Intento de conteo con código de barras incorrecto. Producto ID: {$product->id}, Escaneado: {$scannedBarcode}");
        return response()->json(['message' => 'El código de barras escaneado no corresponde a este producto.'], 422);
    }

    public function processCount(ProcessCountRequest $request, Product $product)
    {
        $productId = $request->input('product_id');
        $countedQuantity = $request->input('counted_quantity');

    }

}
