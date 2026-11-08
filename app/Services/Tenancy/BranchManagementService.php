<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Contracts\Tenancy\BranchManagementServiceInterface;
use App\Models\ExchangeRate;
use App\Models\GeneralSetting;
use App\Models\ProfitabilitySetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Stancl\Tenancy\Database\Models\Domain;

class BranchManagementService implements BranchManagementServiceInterface
{
    public function __construct(
        protected TenantProvisioningService $provisioningService
    ) {}

    /**
     * Lista las sucursales del grupo corporativo.
     */
    public function listGroupBranches(string $currentTenantId): array
    {
        $tenant = Tenant::find($currentTenantId);
        if (!$tenant) {
            return [];
        }

        $rootId = $tenant->parent_tenant_id ?: $tenant->id;

        $groupTenants = Tenant::with('domains')
            ->where('id', $rootId)
            ->orWhere('parent_tenant_id', $rootId)
            ->get();

        $prefix = (string) config('tenancy.database.prefix', 'tovaerp_tenant_');

        return $groupTenants->map(function (Tenant $t) use ($rootId, $prefix) {
            $isMatriz = $t->id === $rootId;
            $firstDomain = $t->domains->first()?->domain ?? "{$t->id}.tovaerp.com";

            return [
                'id' => $t->id,
                'parent_tenant_id' => $t->parent_tenant_id,
                'company_name' => $t->company_name ?? ucfirst($t->id),
                'branch_code' => $t->branch_code ?? ($isMatriz ? 'MATRIZ' : strtoupper($t->id)),
                'branch_address' => $t->branch_address,
                'branch_phone' => $t->branch_phone,
                'is_matriz' => $isMatriz,
                'is_branch_active' => (bool) ($t->is_branch_active ?? true),
                'can_create_branches' => (bool) ($t->can_create_branches ?? false),
                'max_branches' => (int) ($t->max_branches ?? 5),
                'domain' => $firstDomain,
                'url' => 'https://' . $firstDomain,
                'database' => $t->database()->getName(),
                'created_at' => $t->created_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * Crea una nueva sucursal y clona configuración y administradores.
     */
    public function createBranchForPharmacy(string $parentTenantId, array $data): Tenant
    {
        $parent = Tenant::findOrFail($parentTenantId);

        // Si el solicitante ya es una sucursal, referenciar la matriz original
        $matriz = $parent->parent_tenant_id ? Tenant::findOrFail($parent->parent_tenant_id) : $parent;

        if (!$matriz->can_create_branches) {
            throw new RuntimeException('La empresa matriz no tiene autorizada la creación de sucursales.');
        }

        $currentBranchesCount = Tenant::where('parent_tenant_id', $matriz->id)->count();
        if ($currentBranchesCount >= (int) ($matriz->max_branches ?? 5)) {
            throw new RuntimeException("Se ha alcanzado el límite máximo de sucursales permitidas ({$matriz->max_branches}).");
        }

        $rawBranchId = Str::slug((string) ($data['branch_id'] ?? $data['branch_code'] ?? ''));
        if (empty($rawBranchId)) {
            $rawBranchId = 'sede' . ($currentBranchesCount + 1);
        }

        $fullTenantId = strtolower($matriz->id . '_' . $rawBranchId);
        $fullTenantId = preg_replace('/[^a-z0-9_]/', '_', $fullTenantId);

        if (Tenant::where('id', $fullTenantId)->exists()) {
            throw new InvalidArgumentException("El identificador de sucursal '{$fullTenantId}' ya existe.");
        }

        $branchName = (string) ($data['company_name'] ?? $data['branch_name'] ?? ($matriz->company_name . ' - ' . ucfirst($rawBranchId)));
        $branchCode = strtoupper((string) ($data['branch_code'] ?? $rawBranchId));

        // 1. Aprovisionar nuevo tenant con su base de datos y migraciones
        $provisionData = [
            'tenant_id' => $fullTenantId,
            'company_name' => $branchName,
            'vertical' => $matriz->vertical ?: \App\Enums\BusinessVerticalEnum::PHARMACY->value,
            'plan_id' => $matriz->plan_id,
            'domain' => !empty($data['domain']) ? (string) $data['domain'] : "{$fullTenantId}.tovaerp.com",
            'admin_name' => 'admin',
            'admin_email' => "admin@{$fullTenantId}.com",
            'password' => '12345678',
        ];

        $branchTenant = $this->provisioningService->createTenant($provisionData);

        // 2. Asignar metadatos jerárquicos
        $branchTenant->update([
            'parent_tenant_id' => $matriz->id,
            'branch_code' => $branchCode,
            'branch_address' => $data['branch_address'] ?? null,
            'branch_phone' => $data['branch_phone'] ?? null,
            'is_branch_active' => true,
            'can_create_branches' => false,
        ]);

        // 3. Clonar administradores de la matriz y parámetros operativos
        $this->cloneParentAdminsAndSettings($matriz, $branchTenant, (bool) ($data['clone_catalog'] ?? true));

        return $branchTenant;
    }

    /**
     * Actualiza metadatos de una sucursal del grupo.
     */
    public function updateBranch(string $parentTenantId, string $branchId, array $data): Tenant
    {
        $branch = Tenant::where('id', $branchId)
            ->where(function ($q) use ($parentTenantId) {
                $q->where('parent_tenant_id', $parentTenantId)
                  ->orWhere('id', $parentTenantId);
            })
            ->firstOrFail();

        $fillData = [];
        if (!empty($data['company_name'])) {
            $fillData['company_name'] = $data['company_name'];
        }
        if (isset($data['branch_code'])) {
            $fillData['branch_code'] = strtoupper((string) $data['branch_code']);
        }
        if (isset($data['branch_address'])) {
            $fillData['branch_address'] = $data['branch_address'];
        }
        if (isset($data['branch_phone'])) {
            $fillData['branch_phone'] = $data['branch_phone'];
        }

        if (!empty($fillData)) {
            $branch->update($fillData);
        }

        return $branch;
    }

    /**
     * Activa o desactiva la sucursal.
     */
    public function toggleBranchStatus(string $parentTenantId, string $branchId): Tenant
    {
        $branch = Tenant::where('id', $branchId)
            ->where('parent_tenant_id', $parentTenantId)
            ->firstOrFail();

        $branch->update([
            'is_branch_active' => !((bool) $branch->is_branch_active),
        ]);

        return $branch;
    }

    /**
     * Clona los usuarios administradores de la matriz y configuraciones base.
     */
    protected function cloneParentAdminsAndSettings(Tenant $matriz, Tenant $branch, bool $cloneCatalog): void
    {
        $adminsToCopy = [];
        $settingsToCopy = null;
        $profitabilityToCopy = [];
        $exchangeRatesToCopy = [];

        // Leer datos desde la Casa Matriz
        try {
            $matriz->run(function () use (&$adminsToCopy, &$settingsToCopy, &$profitabilityToCopy, &$exchangeRatesToCopy) {
                $adminsToCopy = User::where('role_id', 1)->get()->toArray();
                $settingsToCopy = GeneralSetting::first()?->toArray();
                if (Schema::hasTable('profitability_settings')) {
                    $profitabilityToCopy = DB::table('profitability_settings')->get()->toArray();
                }
                if (Schema::hasTable('exchange_rates')) {
                    $exchangeRatesToCopy = DB::table('exchange_rates')->get()->toArray();
                }
            });
        } catch (\Throwable $e) {
            Log::warning("[BranchManagementService] Error leyendo datos de matriz {$matriz->id}: " . $e->getMessage());
        }

        // Sembrar en la nueva sucursal
        try {
            $branch->run(function () use ($adminsToCopy, $settingsToCopy, $profitabilityToCopy, $exchangeRatesToCopy, $branch) {
                // Sincronizar administradores
                foreach ($adminsToCopy as $adm) {
                    User::updateOrCreate(
                        ['username' => $adm['username']],
                        [
                            'email' => $adm['email'],
                            'password_hash' => $adm['password_hash'],
                            'role_id' => 1,
                            'is_active' => true,
                            'is_corporate_admin' => true,
                            'allowed_branch_ids' => null,
                        ]
                    );
                }

                // Sincronizar configuraciones generales básicas
                if (!empty($settingsToCopy)) {
                    $cleanSettings = $settingsToCopy;
                    unset($cleanSettings['id'], $cleanSettings['created_at'], $cleanSettings['updated_at']);
                    $cleanSettings['app_name'] = $branch->company_name ?? ($cleanSettings['app_name'] ?? 'ERP Farmacia');
                    if (isset($cleanSettings['address']) && !empty($branch->branch_address)) {
                        $cleanSettings['address'] = $branch->branch_address;
                    }
                    GeneralSetting::updateOrCreate(['id' => 1], $cleanSettings);
                }

                // Sincronizar configuraciones de rentabilidad
                if (!empty($profitabilityToCopy) && Schema::hasTable('profitability_settings')) {
                    foreach ($profitabilityToCopy as $prof) {
                        $pArr = (array) $prof;
                        unset($pArr['id']);
                        DB::table('profitability_settings')->updateOrInsert(
                            ['category_id' => $pArr['category_id'] ?? null, 'origin_id' => $pArr['origin_id'] ?? null],
                            $pArr
                        );
                    }
                }

                // Sincronizar tasas de cambio
                if (!empty($exchangeRatesToCopy) && Schema::hasTable('exchange_rates')) {
                    foreach ($exchangeRatesToCopy as $rate) {
                        $rArr = (array) $rate;
                        unset($rArr['id']);
                        DB::table('exchange_rates')->updateOrInsert(
                            ['currency' => $rArr['currency'] ?? 'USD'],
                            $rArr
                        );
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::warning("[BranchManagementService] Error sembrando datos en sucursal {$branch->id}: " . $e->getMessage());
        }

        // 4. Opcional: Clonar catálogo activo con stock en 0
        if ($cloneCatalog) {
            $this->cloneActiveCatalogFromParent($matriz, $branch);
        }
    }

    /**
     * Clona el catálogo de productos de la matriz hacia la sucursal con stock 0.
     */
    protected function cloneActiveCatalogFromParent(Tenant $matriz, Tenant $branch): void
    {
        try {
            $productsToClone = [];
            $matriz->run(function () use (&$productsToClone) {
                if (Schema::hasTable('products')) {
                    $productsToClone = DB::table('products')
                        ->where('is_deleted', 0)
                        ->where('is_active', 1)
                        ->limit(5000)
                        ->get()
                        ->toArray();
                }
            });

            if (empty($productsToClone)) {
                return;
            }

            $branch->run(function () use ($productsToClone) {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                foreach ($productsToClone as $prod) {
                    $p = (array) $prod;
                    $p['stock'] = 0;
                    $p['sales_average'] = 0;
                    $p['sales_average_weighted'] = 0;
                    DB::table('products')->updateOrInsert(['id' => $p['id']], $p);
                }
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            });
        } catch (\Throwable $e) {
            Log::warning("[BranchManagementService] Error clonando catálogo a sucursal {$branch->id}: " . $e->getMessage());
        }
    }
}
