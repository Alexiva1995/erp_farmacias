<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\AddTenantDomainRequest;
use App\Http\Requests\Central\CreateTenantRequest;
use App\Http\Requests\Central\LinkTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\Tenancy\TenantManagementService;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantManagementController extends Controller
{
    public function __construct(
        protected TenantProvisioningService $provisioningService,
        protected TenantManagementService $managementService
    ) {}

    /**
     * Listado de tenants registrados y bases de datos físicas en MySQL (incluyendo huérfanas).
     */
    public function index(): JsonResponse
    {
        try {
            // Auto-reparar registros y usuarios si aplica antes del listado
            if (\Illuminate\Support\Facades\Schema::hasTable('tenants')) {
                $tenants = Tenant::with('domains')->get();
                foreach ($tenants as $tenant) {
                    if ($tenant->domains->isEmpty() && !empty($tenant->id)) {
                        try {
                            $expectedDomain = "{$tenant->id}.tovaerp.com";
                            \Stancl\Tenancy\Database\Models\Domain::where('domain', $expectedDomain)->delete();
                            $tenant->domains()->create(['domain' => $expectedDomain]);
                        } catch (\Throwable) {
                        }
                    }
                }
            }

            $allTenants = $this->managementService->listAllTenantsAndOrphans();

            return response()->json([
                'data' => $allTenants,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Tenant fetch error: ' . $e->getMessage());
            return response()->json(['data' => []]);
        }
    }

    /**
     * Crear y aprovisionar un nuevo Tenant (Farmacia).
     */
    public function store(CreateTenantRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $domain = !empty($validated['domain']) ? (string) $validated['domain'] : (string) ($validated['tenant_id'] ?? '');
            $validated['domain'] = $domain;

            $tenant = $this->provisioningService->createTenant($validated);
            $tenant->load('domains');

            return response()->json([
                'message' => 'Empresa aprovisionada exitosamente con todos los catálogos y formatos maestros.',
                'tenant'  => new TenantResource($tenant),
                'login_url' => 'https://' . ($tenant->domains->first()?->domain ?? "{$tenant->id}.tovaerp.com"),
            ], 201);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error aprovisionando tenant: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al aprovisionar la empresa: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Vincular una base de datos física existente a un tenant.
     */
    public function link(LinkTenantRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->managementService->linkExistingDatabase($validated);

            return response()->json($result);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error vinculando tenant: ' . $e->getMessage());

            return response()->json([
                'message' => 'Error al vincular la empresa: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Eliminar un Tenant, sus dominios y su base de datos física de MySQL.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $databaseName = $request->input('database');
            $result = $this->managementService->deleteTenantAndDatabase($id, $databaseName ? (string) $databaseName : null);

            return response()->json($result);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error eliminando tenant {$id}: " . $e->getMessage());

            return response()->json([
                'message' => 'Error al eliminar la empresa: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Agregar un dominio a un tenant existente.
     */
    public function addDomain(AddTenantDomainRequest $request, string $id): JsonResponse
    {
        try {
            $domain = (string) $request->validated('domain');
            $result = $this->managementService->addDomain($id, $domain);

            return response()->json($result, 201);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error agregando dominio a tenant {$id}: " . $e->getMessage());

            return response()->json([
                'message' => 'Error al agregar el dominio: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Eliminar un dominio de un tenant existente.
     */
    public function removeDomain(string $id, string $domainId): JsonResponse
    {
        try {
            $result = $this->managementService->removeDomain($id, $domainId);

            return response()->json($result);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error eliminando dominio {$domainId} de tenant {$id}: " . $e->getMessage());

            return response()->json([
                'message' => 'Error al eliminar el dominio: ' . $e->getMessage(),
            ], 422);
        }
    }
}
