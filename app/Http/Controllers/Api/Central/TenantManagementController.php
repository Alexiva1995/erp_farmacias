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
    public function index(): AnonymousResourceCollection
    {
        $tenants = Tenant::with('domains')
            ->orderBy('created_at', 'desc')
            ->get();

        return TenantResource::collection($tenants);
    }

    /**
     * Crear y aprovisionar un nuevo Tenant (Farmacia).
     */
    public function store(CreateTenantRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['domain'] = $validated['domain'] ?? $validated['tenant_id'];

        $tenant = $this->provisioningService->createTenant($validated);
        $tenant->load('domains');

        return response()->json([
            'message' => 'Farmacia aprovisionada exitosamente con todos los catálogos y formatos maestros.',
            'tenant'  => new TenantResource($tenant),
            'login_url' => 'https://' . ($tenant->domains->first()?->domain ?? "{$tenant->id}.tovaerp.com"),
        ], 201);
    }
}
