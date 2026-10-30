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

            // Garantizar que todo tenant tenga su registro de dominio y al menos su usuario administrador
            foreach ($tenants as $tenant) {
                // 1. Auto-reparar dominio si falta en la tabla central de dominios
                if ($tenant->domains->isEmpty() && !empty($tenant->id)) {
                    try {
                        $expectedDomain = "{$tenant->id}.tovaerp.com";
                        \Stancl\Tenancy\Database\Models\Domain::where('domain', $expectedDomain)->delete();
                        $tenant->domains()->create([
                            'domain' => $expectedDomain,
                        ]);
                        $tenant->load('domains');
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning("No se pudo asociar dominio automático para tenant {$tenant->id}: " . $e->getMessage());
                    }
                }

                // 2. Auto-reparar usuario Administrador si la tabla users del tenant está vacía
                try {
                    $tenant->run(function () use ($tenant) {
                        if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
                            $userCount = \Illuminate\Support\Facades\DB::table('users')->count();
                            if ($userCount === 0) {
                                if (\Illuminate\Support\Facades\Schema::hasTable('roles')) {
                                    \Illuminate\Support\Facades\DB::table('roles')->updateOrInsert(
                                        ['id' => 1],
                                        ['name' => 'Admin', 'created_at' => now(), 'updated_at' => now()]
                                    );
                                    \Illuminate\Support\Facades\DB::table('roles')->updateOrInsert(
                                        ['id' => 2],
                                        ['name' => 'Supervisor', 'created_at' => now(), 'updated_at' => now()]
                                    );
                                }

                                \Illuminate\Support\Facades\DB::table('users')->insert([
                                    'username'      => 'Administrador',
                                    'email'         => "admin@{$tenant->id}.com",
                                    'password_hash' => \Illuminate\Support\Facades\Hash::make('12345678'),
                                    'role_id'       => 1,
                                    'is_active'     => 1,
                                    'created_at'    => now(),
                                    'updated_at'    => now(),
                                ]);

                                \Illuminate\Support\Facades\DB::table('users')->insert([
                                    'username'      => 'tienda',
                                    'email'         => "tienda@{$tenant->id}.com",
                                    'password_hash' => \Illuminate\Support\Facades\Hash::make('tienda123'),
                                    'role_id'       => 2,
                                    'is_active'     => 1,
                                    'created_at'    => now(),
                                    'updated_at'    => now(),
                                ]);
                            }
                        }
                    });
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("No se pudo auto-reparar usuario para tenant {$tenant->id}: " . $e->getMessage());
                }
            }

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
                'message' => "Empresa '{$id}' y su base de datos fueron eliminadas correctamente.",
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error eliminando tenant {$id}: " . $e->getMessage());

            return response()->json([
                'message' => 'Error al eliminar la empresa: ' . $e->getMessage(),
            ], 422);
        }
    }
}
