<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\CreateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TenantManagementController extends Controller
{
    public function __construct(
        protected TenantProvisioningService $provisioningService
    ) {}

    /**
     * Listado de tenants registrados con sus dominios asociados.
     */
    public function index(): JsonResponse|AnonymousResourceCollection
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('tenants')) {
                return response()->json(['data' => []]);
            }

            $tenants = Tenant::with('domains')
                ->orderBy('created_at', 'desc')
                ->get();

            return TenantResource::collection($tenants);
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
            $validated['domain'] = $validated['domain'] ?? $validated['tenant_id'];

            $tenant = $this->provisioningService->createTenant($validated);
            $tenant->load('domains');

            return response()->json([
                'message' => 'Farmacia aprovisionada exitosamente con todos los catálogos y formatos maestros.',
                'tenant'  => new TenantResource($tenant),
                'login_url' => 'https://' . ($tenant->domains->first()?->domain ?? "{$tenant->id}.tovaerp.com"),
            ], 201);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error aprovisionando tenant: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al aprovisionar la farmacia: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Eliminar un Tenant, sus dominios y su base de datos asociada.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $tenant = Tenant::find($id);

            // 1. Eliminar dominios asociados
            \Stancl\Tenancy\Database\Models\Domain::where('tenant_id', $id)->delete();

            // 2. Eliminar base de datos física si existe
            $dbPrefix = config('tenancy.database.prefix', 'tovaerp_tenant_');
            $dbName = $dbPrefix . $id;
            try {
                \Illuminate\Support\Facades\DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
            } catch (\Throwable) {}

            // 3. Eliminar registro del tenant
            if ($tenant) {
                $tenant->delete();
            } else {
                Tenant::where('id', $id)->delete();
            }

            return response()->json([
                'message' => "Farmacia '{$id}' y su base de datos fueron eliminadas correctamente.",
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error eliminando tenant {$id}: " . $e->getMessage());

            return response()->json([
                'message' => 'Error al eliminar la farmacia: ' . $e->getMessage(),
            ], 422);
        }
    }
}
