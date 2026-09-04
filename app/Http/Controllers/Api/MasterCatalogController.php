<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Catalog\MasterCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterCatalogController extends Controller
{
    public function __construct(
        protected MasterCatalogService $masterCatalogService
    ) {}

    /**
     * Buscar producto en el catálogo maestro por código de barra.
     */
    public function lookup(Request $request): JsonResponse
    {
        $barcode = (string) $request->query('barcode', '');
        $result = $this->masterCatalogService->lookupByBarcode($barcode);

        return response()->json($result);
    }

    /**
     * Buscar múltiples productos en el catálogo maestro por lista de códigos de barra (Bulk Lookup).
     */
    public function lookupBulk(Request $request): JsonResponse
    {
        $barcodes = (array) ($request->input('barcodes') ?? []);
        $results = $this->masterCatalogService->lookupBulk($barcodes);

        return response()->json([
            'success' => true,
            'data'    => $results,
        ]);
    }

    /**
     * Exportar en lote laboratorios, categorías, orígenes, grupos y proveedores hacia farmacias esclavas.
     */
    public function bulkExport(Request $request): JsonResponse
    {
        $entities = explode(',', (string) $request->query('entities', 'laboratories,origins,groups,categories,suppliers'));
        $data = $this->masterCatalogService->exportEntities($entities);

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Registrar un producto nuevo en el catálogo maestro y obtener ID oficial.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'barcode'           => 'nullable|string|max:100',
            'active_ingredient' => 'nullable|string|max:255',
            'laboratory_name'   => 'nullable|string|max:255',
            'laboratory_id'     => 'nullable|integer',
            'origin_name'       => 'nullable|string|max:255',
            'origin_id'         => 'nullable|integer',
            'category_name'     => 'nullable|string|max:255',
            'category_id'       => 'nullable|integer',
            'description'       => 'nullable|string',
            'presentation'      => 'nullable|string|max:255',
            'unit_of_measure'   => 'nullable|string|max:100',
            'photo_url'         => 'nullable|string',
            'unit_cost'         => 'nullable|numeric',
            'sale_price'        => 'nullable|numeric',
            'psychotropic'      => 'nullable|boolean',
            'iva'               => 'nullable|numeric',
        ]);

        $result = $this->masterCatalogService->registerMasterProduct($data);

        return response()->json($result, $result['created'] ? 201 : 200);
    }

    /**
     * Registrar o asegurar un laboratorio en el catálogo maestro.
     */
    public function storeLaboratory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'group_id'  => 'nullable|integer',
            'parent_id' => 'nullable|integer',
        ]);

        $result = $this->masterCatalogService->registerMasterLaboratory($data);

        return response()->json($result, $result['created'] ? 201 : 200);
    }

    /**
     * Registrar o asegurar un grupo en el catálogo maestro.
     */
    public function storeGroup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $result = $this->masterCatalogService->registerMasterGroup($data);

        return response()->json($result, $result['created'] ? 201 : 200);
    }

    /**
     * Registrar o asegurar un proveedor en el catálogo maestro.
     */
    public function storeSupplier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'             => 'required|string|max:255',
            'social_reason'    => 'nullable|string|max:255',
            'rif'              => 'nullable|string|max:50',
            'sales_phone'      => 'nullable|string|max:50',
            'collections_phone'=> 'nullable|string|max:50',
            'credit_days'      => 'nullable|integer',
            'dispatch_days'    => 'nullable|array',
            'order_days'       => 'nullable|array',
            'payment_method'   => 'nullable|string|max:50',
            'cash_payment'     => 'nullable|boolean',
            'charges_igtf'     => 'nullable|boolean',
        ]);

        $result = $this->masterCatalogService->registerMasterSupplier($data);

        return response()->json($result, $result['created'] ? 201 : 200);
    }

    /**
     * Registrar o asegurar un origen en el catálogo maestro.
     */
    public function storeOrigin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $result = $this->masterCatalogService->registerMasterOrigin($data);

        return response()->json($result, $result['created'] ? 201 : 200);
    }

    /**
     * Registrar o asegurar una categoría en el catálogo maestro.
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $result = $this->masterCatalogService->registerMasterCategory($data);

        return response()->json($result, $result['created'] ? 201 : 200);
    }
}

