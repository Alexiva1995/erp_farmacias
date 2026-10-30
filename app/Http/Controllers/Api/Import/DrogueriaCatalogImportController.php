<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Import;

use App\Http\Controllers\Controller;
use App\Http\Requests\Import\ImportDrogueriaMasterCatalogRequest;
use App\Services\Import\DrogueriaMasterCatalogImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DrogueriaCatalogImportController extends Controller
{
    public function __construct(
        protected DrogueriaMasterCatalogImportService $importService
    ) {}

    /**
     * Lista los proveedores del Master con el total de productos en catálogo.
     */
    public function getMasterSuppliers(): JsonResponse
    {
        $suppliers = $this->importService->getMasterSuppliersWithCatalogCount();

        return response()->json([
            'success' => true,
            'data' => $suppliers,
        ]);
    }

    /**
     * Muestra la previsualización del catálogo del proveedor seleccionado.
     */
    public function previewCatalog(int $supplierId, Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 20);
        $preview = $this->importService->previewSupplierCatalog($supplierId, $limit);

        return response()->json($preview);
    }

    /**
     * Ejecuta la importación del catálogo hacia el inventario local.
     */
    public function executeImport(ImportDrogueriaMasterCatalogRequest $request): JsonResponse
    {
        $supplierId = (int) $request->input('master_supplier_id');
        $options = $request->validated();

        $result = $this->importService->importCatalogToDrogueriaInventory($supplierId, $options);

        return response()->json($result);
    }
}
