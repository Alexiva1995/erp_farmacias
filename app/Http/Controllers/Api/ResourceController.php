<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLaboratoryRequest;
use App\Http\Requests\StoreOriginRequest;
use App\Models\Laboratory;
use App\Models\Origin;
use App\Services\Resources\ResourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\Product;

class ResourceController extends Controller
{
    public function __construct(private ResourceService $resourceService) {}

    public function getLaboratories()
    {
        $laboratories = $this->resourceService->getLaboratories();
        return response()->json($laboratories);
    }

    public function storeLaboratory(StoreLaboratoryRequest $request)
    {
        $data = $request->validated();

        if (config('catalog.role') === 'slave') {
            try {
                $masterClient = app(\App\Services\Catalog\MasterCatalogClientService::class);
                $masterLab = $masterClient->registerLaboratoryInMaster($data);
                if (!empty($masterLab['id'])) {
                    $data['id'] = (int) $masterLab['id'];
                }
            } catch (\Throwable $e) {
                \Log::warning('No se pudo sincronizar laboratorio con Master Catalog: ' . $e->getMessage());
            }
        }

        $laboratory = Laboratory::create($data);

        // Limpiar la caché de laboratorios para que se actualice la lista
        \Cache::forget('resources.laboratories');

        return response()->json([
            'message' => 'Laboratorio creado con éxito.',
            'laboratory' => $laboratory,
        ], 201);
    }

    public function storeOrigin(StoreOriginRequest $request)
    {
        $data = $request->validated();

        if (config('catalog.role') === 'slave') {
            try {
                $masterClient = app(\App\Services\Catalog\MasterCatalogClientService::class);
                $masterOrigin = $masterClient->registerOriginInMaster($data);
                if (!empty($masterOrigin['id'])) {
                    $data['id'] = (int) $masterOrigin['id'];
                }
            } catch (\Throwable $e) {
                \Log::warning('No se pudo sincronizar origen con Master Catalog: ' . $e->getMessage());
            }
        }

        $origin = Origin::create($data);

        // Limpiar la caché de orígenes para que se actualice la lista
        \Cache::forget('resources.origins');

        return response()->json([
            'message' => 'Origen creado con éxito.',
            'origin' => $origin,
        ], 201);
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
        $categories = $this->resourceService->getCategories(request()->only('type'));
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

    public function findProductById(Product $product): JsonResponse
    {
        try {
            $detailedProduct = $this->resourceService->loadProductDetails($product);
            return response()->json($detailedProduct);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Producto no encontrado.'], 404);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Ocurrió un error inesperado.'], 500);
        }
    }

    public function getExchangeRates(): JsonResponse
    {
        $rates = $this->resourceService->getAllExchangeRate();
        return response()->json($rates);
    }
    public function getAllProducts()
    {
        $products = $this->resourceService->getAllProducts();
        return response()->json($products);
    }
}
