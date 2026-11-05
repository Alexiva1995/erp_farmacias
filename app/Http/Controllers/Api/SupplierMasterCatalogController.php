<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\ActivateMasterSupplierRequest;
use App\Http\Resources\MasterSupplierCatalogResource;
use App\Http\Resources\SupplierResource;
use App\Services\Suppliers\SupplierMasterSyncService;
use App\Services\Tenancy\MasterSupplierSeederService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierMasterCatalogController extends Controller
{
    public function __construct(
        protected SupplierMasterSyncService $masterSyncService,
        protected MasterSupplierSeederService $masterSeederService
    ) {}

    /**
     * Retorna el catálogo maestro de proveedores cruzado con el estado del tenant actual.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status', 'all'),
            'type' => $request->input('type', 'drogueria'),
            'page' => (int) $request->input('page', 1),
            'per_page' => (int) $request->input('per_page', 25),
        ];

        $catalog = $this->masterSyncService->getMasterCatalog($filters);

        return response()->json([
            'success' => true,
            'data' => MasterSupplierCatalogResource::collection(collect($catalog['data']))->resolve(),
            'meta' => [
                'total' => $catalog['total'],
                'page' => $catalog['page'],
                'per_page' => $catalog['per_page'],
                'last_page' => (int) ceil($catalog['total'] / max(1, $catalog['per_page'])),
            ],
        ]);
    }

    /**
     * Activa un proveedor del Master en el Tenant local copiando su plantilla de conexión.
     */
    public function activate(ActivateMasterSupplierRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->masterSyncService->activateMasterSupplier(
            masterSupplierId: (int) $validated['master_supplier_id'],
            senderEmail: $validated['sender_email'] ?? null,
            creditDays: isset($validated['credit_days']) ? (int) $validated['credit_days'] : null,
            isActive: isset($validated['is_active']) ? (bool) $validated['is_active'] : true
        );

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'supplier' => new SupplierResource($result['supplier']),
        ]);
    }

    /**
     * Sincroniza y consolida proveedores y mapeos de Excel de todos los tenants hacia el Master.
     */
    public function syncFromAllTenants(Request $request): JsonResponse
    {
        $overwrite = $request->boolean('overwrite', true);
        $propagate = $request->boolean('propagate', false);

        $stats = $this->masterSeederService->syncMasterFromAllTenants($overwrite);

        if ($propagate) {
            $this->masterSeederService->propagateToAllTenants();
        }

        return response()->json([
            'success' => true,
            'message' => "Sincronización consolidada con éxito: {$stats['suppliers_synced']} proveedores y {$stats['connections_synced']} estructuras consolidadas en tovaerp_master.",
            'data' => $stats,
        ]);
    }
}
