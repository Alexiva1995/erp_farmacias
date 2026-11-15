<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Database\Models\Domain;

class TenantManagementService
{
    /**
     * Listar todos los tenants registrados y detectar bases de datos huérfanas en MySQL.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAllTenantsAndOrphans(): array
    {
        $result = [];
        $registeredDbNames = [];

        // 1. Obtener tenants registrados
        if (Schema::hasTable('tenants')) {
            $tenants = Tenant::with('domains')
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($tenants as $tenant) {
                /** @var Tenant $tenant */
                $isRemote = (bool) ($tenant->is_remote ?? false);
                $dbName = $isRemote ? null : $tenant->database()->getName();
                if ($dbName !== null) {
                    $registeredDbNames[] = strtolower($dbName);
                }

                $resource = (new TenantResource($tenant))->resolve();
                $resource['database'] = $dbName;
                $resource['is_orphan'] = false;

                $result[] = $resource;
            }
        }

        // 2. Obtener bases de datos físicas en MySQL
        try {
            $prefix = (string) config('tenancy.database.prefix', 'tovaerp_tenant_');
            $rawDatabases = collect(DB::select('SHOW DATABASES'))->pluck('Database')->values();

            // Filtrar bases de datos sospechosas de tenants del ERP
            $tenantDatabases = $rawDatabases->filter(function (string $dbName) use ($prefix, $registeredDbNames): bool {
                $lower = strtolower($dbName);
                return str_starts_with($lower, strtolower($prefix))
                    || str_starts_with($lower, 'tovaerp_')
                    || str_contains($lower, 'tenant')
                    || in_array($lower, $registeredDbNames, true);
            });

            // 3. Agregar como elementos virtuales las bases de datos huérfanas
            foreach ($tenantDatabases as $dbName) {
                if (!in_array(strtolower($dbName), $registeredDbNames, true)) {
                    // Deducir un identificador limpio
                    $cleanId = str_starts_with($dbName, $prefix)
                        ? substr($dbName, strlen($prefix))
                        : (str_starts_with($dbName, 'tovaerp_') ? substr($dbName, strlen('tovaerp_')) : $dbName);

                    $result[] = [
                        'id' => $cleanId,
                        'company_name' => "BD Huérfana ({$dbName})",
                        'database' => $dbName,
                        'vertical' => 'orphan',
                        'is_orphan' => true,
                        'domains' => [],
                        'created_at' => null,
                        'updated_at' => null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudieron escanear bases de datos de MySQL para tenants: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Eliminar un tenant, sus dominios y su base de datos física de MySQL.
     *
     * @param string $id
     * @param string|null $databaseName
     * @return array<string, mixed>
     */
    public function deleteTenantAndDatabase(string $id, ?string $databaseName = null): array
    {
        $tenant = Tenant::find($id);
        $isRemote = (bool) ($tenant?->is_remote ?? false);

        // 1. Determinar el nombre exacto de la base de datos
        $dbPrefix = (string) config('tenancy.database.prefix', 'tovaerp_tenant_');
        $dbName = $databaseName;

        if (!$isRemote && empty($dbName)) {
            if ($tenant) {
                $dbName = $tenant->database()->getName();
            } else {
                $dbName = str_starts_with($id, 'tovaerp_') ? $id : $dbPrefix . $id;
            }
        }

        // 2. Eliminar dominios asociados en base central
        Domain::where('tenant_id', $id)->delete();

        // 3. Eliminar base de datos física en MySQL solo si no es remoto
        if (!$isRemote && !empty($dbName)) {
            try {
                DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
            } catch (\Throwable $e) {
                Log::error("Error al ejecutar DROP DATABASE para `{$dbName}`: " . $e->getMessage());
            }
        }

        // 4. Eliminar registro del tenant y su suscripción en base central
        try {
            if (Schema::hasTable('saas_subscriptions')) {
                \App\Models\SaasSubscription::where('tenant_id', $id)->delete();
            }
        } catch (\Throwable) {
        }

        if ($tenant) {
            $tenant->delete();
        } else {
            Tenant::where('id', $id)->delete();
        }

        return [
            'success' => true,
            'message' => "La empresa '{$id}' y su base de datos '{$dbName}' fueron eliminadas permanentemente.",
        ];
    }

    /**
     * Vincular una base de datos física existente a un tenant sin recrearla.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function linkExistingDatabase(array $data): array
    {
        $tenantId = strtolower(trim((string) $data['tenant_id']));
        $dbName = trim((string) $data['database']);
        $companyName = !empty($data['company_name']) ? (string) $data['company_name'] : ucfirst($tenantId);
        $vertical = !empty($data['vertical']) ? (string) $data['vertical'] : \App\Enums\BusinessVerticalEnum::PHARMACY->value;
        $rawDomain = !empty($data['domain']) ? (string) $data['domain'] : "{$tenantId}.tovaerp.com";
        $domain = str_contains($rawDomain, '.') ? $rawDomain : "{$rawDomain}.tovaerp.com";

        // 1. Crear o actualizar Tenant suprimiendo eventos destructivos
        /** @var Tenant $tenant */
        $tenant = Tenant::withoutEvents(function () use ($tenantId, $companyName, $dbName, $vertical) {
            return Tenant::updateOrCreate(
                ['id' => $tenantId],
                [
                    'company_name' => $companyName,
                    'tenancy_db_name' => $dbName,
                    'vertical' => $vertical,
                ]
            );
        });

        // 2. Asociar dominio en tabla central
        Domain::updateOrCreate(
            ['tenant_id' => $tenantId],
            ['domain' => $domain]
        );

        // 3. Ejecutar migraciones pendientes en la base de datos vinculada
        try {
            \Illuminate\Support\Facades\Artisan::call('tenants:migrate', [
                '--tenants' => [$tenant->id],
                '--force' => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning("Advertencia al migrar base de datos vinculada '{$dbName}': " . $e->getMessage());
        }

        return [
            'success' => true,
            'message' => "La empresa '{$companyName}' fue vinculada exitosamente a la base de datos '{$dbName}'.",
            'tenant' => (new TenantResource($tenant))->resolve(),
        ];
    }

    /**
     * Agregar un dominio a un tenant existente.
     *
     * @param string $tenantId
     * @param string $rawDomain
     * @return array<string, mixed>
     */
    public function addDomain(string $tenantId, string $rawDomain): array
    {
        $tenant = Tenant::findOrFail($tenantId);
        $cleanDomain = strtolower(trim($rawDomain));
        $domain = str_contains($cleanDomain, '.') ? $cleanDomain : "{$cleanDomain}.tovaerp.com";

        $createdDomain = Domain::create([
            'tenant_id' => $tenant->id,
            'domain' => $domain,
        ]);

        return [
            'success' => true,
            'message' => "Dominio '{$domain}' agregado exitosamente a la empresa '{$tenant->id}'.",
            'domain' => [
                'id' => $createdDomain->id,
                'domain' => $createdDomain->domain,
            ],
        ];
    }

    /**
     * Eliminar un dominio de un tenant.
     *
     * @param string $tenantId
     * @param string|int $domainId
     * @return array<string, mixed>
     */
    public function removeDomain(string $tenantId, string|int $domainId): array
    {
        $domain = Domain::where('tenant_id', $tenantId)
            ->where(function ($query) use ($domainId) {
                if (is_numeric($domainId)) {
                    $query->where('id', (int) $domainId);
                } else {
                    $query->where('domain', $domainId);
                }
            })
            ->firstOrFail();

        $domainName = $domain->domain;
        $domain->delete();

        return [
            'success' => true,
            'message' => "Dominio '{$domainName}' eliminado exitosamente.",
        ];
    }

    /**
     * Registrar un tenant externo (alojado en otro servidor/dominio sin BD local).
     *
     * @param array<string, mixed> $data
     */
    public function createExternalTenant(array $data): Tenant
    {
        $cleanDomain = preg_replace('#^https?://#i', '', trim((string) $data['domain']));
        $cleanDomain = rtrim((string) $cleanDomain, '/');

        // Crear el registro sin disparar eventos de creación de BD local
        $tenant = Tenant::withoutEvents(function () use ($data) {
            return Tenant::create([
                'id' => (string) $data['tenant_id'],
                'company_name' => (string) $data['company_name'],
                'vertical' => (string) ($data['vertical'] ?? 'pharmacy'),
                'is_remote' => true,
                'plan' => (string) $data['plan'],
                'monthly_price' => (float) $data['monthly_price'],
                'billing_date' => (string) $data['billing_date'],
            ]);
        });

        Domain::where('domain', $cleanDomain)->delete();
        $tenant->domains()->create(['domain' => $cleanDomain]);
        $tenant->load('domains');

        try {
            $data['is_remote'] = true;
            $data['domain'] = $cleanDomain;
            app(\App\Contracts\Tenancy\SaasSubscriptionServiceInterface::class)->syncTenantSubscription($tenant, $data);
        } catch (\Throwable $e) {
            Log::warning("Error sincronizando suscripción de tenant externo {$tenant->id}: " . $e->getMessage());
        }

        return $tenant;
    }

    /**
     * Actualizar el plan, costo mensual y fecha de cobro de un tenant.
     *
     * @param string $id
     * @param array<string, mixed> $data
     */
    public function updateTenantBilling(string $id, array $data): Tenant
    {
        $tenant = Tenant::with('domains')->findOrFail($id);
        $tenant->plan = (string) $data['plan'];
        $tenant->plan_id = (string) $data['plan'];
        $tenant->monthly_price = (float) $data['monthly_price'];
        $tenant->billing_date = $data['billing_date'] ?? null;
        if (isset($data['max_branches'])) {
            $tenant->max_branches = (int) $data['max_branches'];
        }
        if (isset($data['can_create_branches'])) {
            $tenant->can_create_branches = (bool) $data['can_create_branches'];
        }
        if (isset($data['status'])) {
            $statusEnum = \App\Enums\SaasSubscriptionStatusEnum::tryFrom((string) $data['status']);
            if ($statusEnum) {
                $tenant->is_branch_active = $statusEnum->canOperate();
            }
        }
        if (!empty($data['created_at'])) {
            $tenant->created_at = \Carbon\Carbon::parse((string) $data['created_at']);
        }
        $tenant->save();

        try {
            app(\App\Contracts\Tenancy\SaasSubscriptionServiceInterface::class)->syncTenantSubscription($tenant, $data);
        } catch (\Throwable $e) {
            Log::warning("Error sincronizando suscripción SaaS al actualizar cobro de {$id}: " . $e->getMessage());
        }

        return $tenant;
    }
}
