<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Resources\ResourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ResourceController extends Controller
{
    public function __construct(private ResourceService $resourceService)
    {
    }

    public function getLaboratories()
    {
        $laboratories = $this->resourceService->getLaboratories();
        return response()->json($laboratories);
    }

    public function getOrigins()
    {
        $origins = $this->resourceService->getOrigins();
        return response()->json($origins);
    }



    public function getSuppliers()
    {
        $suppliers = $this->resourceService->getSuppliers();
        return response()->json($suppliers);
    }

    public function getCategories()
    {
        $categories = $this->resourceService->getCategories();
        return response()->json($categories);
    }


    public function findProductByBarcode(string $barcode): JsonResponse
    {
        try {
            $product = $this->resourceService->getProductByBarcode($barcode);
            return response()->json($product);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Ocurrió un error inesperado.'], 500);
        }
    }
    public function getAllProducts()
    {
        $products = $this->resourceService->getAllProducts();
        return response()->json($products);
    }
}
