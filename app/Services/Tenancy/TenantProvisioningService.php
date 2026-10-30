<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TenantProvisioningService
{
    public function __construct(
        protected OriginSyncService $originSyncService,
        protected CategorySyncService $categorySyncService,
        protected LaboratorySyncService $laboratorySyncService,
        protected GroupSyncService $groupSyncService,
        protected SupplierSyncService $supplierSyncService
    ) {}

    /**
     * Crear un nuevo Tenant con su base de datos y usuario administrador.
     *
     * @param array<string, mixed> $data
     * @return Tenant
     */
    public function createTenant(array $data): Tenant
    {
        // 0. Si existía un registro incompleto previo, limpiarlo sin disparar eventos de base de datos
        \Stancl\Tenancy\Database\Models\Domain::whereIn('tenant_id', [$data['tenant_id'], '0'])->delete();
        Tenant::whereIn('id', [$data['tenant_id'], '0'])->delete();

        // 0.1 Limpiar base de datos huérfana previa si existía
        $dbPrefix = config('tenancy.database.prefix', 'tovaerp_tenant_');
        $dbName = $dbPrefix . $data['tenant_id'];
        try {
            \Illuminate\Support\Facades\DB::statement("DROP DATABASE IF EXISTS `{$dbName}`");
        } catch (\Throwable) {
        }

        // 1. Crear el registro del Tenant (gatilla la creación y migración automática de la BD)
        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'id' => $data['tenant_id'],
            'company_name' => $data['company_name'] ?? $data['tenant_id'],
            'vertical' => $data['vertical'] ?? \App\Enums\BusinessVerticalEnum::PHARMACY->value,
            'plan_id' => $data['plan_id'] ?? null,
        ]);

        // 2. Asociar el dominio / subdominio
        $rawDomain = !empty($data['domain']) ? (string) $data['domain'] : (string) ($data['tenant_id'] ?? '');
        $domain = str_contains($rawDomain, '.') ? $rawDomain : "{$rawDomain}.tovaerp.com";

        try {
            \Stancl\Tenancy\Database\Models\Domain::where('domain', $domain)
                ->orWhereIn('tenant_id', [$data['tenant_id'], '0'])
                ->delete();

            $tenant->domains()->create([
                'domain' => $domain,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Error creando dominio {$domain} para tenant {$data['tenant_id']}: " . $e->getMessage());
        }

        // 3. Sembrar datos maestros y configurar usuario administrador dentro del contexto del Tenant
        $tenant->run(function () use ($data) {
            // 3.1 Sembrar roles primero
            try {
                if (class_exists(\Database\Seeders\RolesSeeder::class)) {
                    app(\Database\Seeders\RolesSeeder::class)->run();
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Error sembrando roles en tenant {$data['tenant_id']}: " . $e->getMessage());
            }

            // Asegurar que exista el rol 1 (Admin) en la tabla roles
            if (\Illuminate\Support\Facades\Schema::hasTable('roles')) {
                \Illuminate\Support\Facades\DB::table('roles')->updateOrInsert(
                    ['id' => 1],
                    [
                        'name' => 'Admin',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
                \Illuminate\Support\Facades\DB::table('roles')->updateOrInsert(
                    ['id' => 2],
                    [
                        'name' => 'Supervisor',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            // 3.2 Crear o actualizar usuario Administrador principal
            $adminUsername = !empty($data['admin_name']) ? $data['admin_name'] : 'admin';
            $adminEmail = !empty($data['admin_email']) ? $data['admin_email'] : 'admin@tovaerp.com';
            $rawPassword = !empty($data['password']) ? $data['password'] : '12345678';
            $hashedPassword = \Illuminate\Support\Facades\Hash::needsRehash($rawPassword)
                ? \Illuminate\Support\Facades\Hash::make($rawPassword)
                : $rawPassword;

            try {
                User::updateOrCreate(
                    ['username' => $adminUsername],
                    [
                        'email'         => $adminEmail,
                        'password_hash' => $hashedPassword,
                        'role_id'       => 1, // Rol Admin
                        'is_active'     => true,
                        'token_login'   => null,
                    ]
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Error creando usuario admin con Eloquent en {$data['tenant_id']}: " . $e->getMessage());
                \Illuminate\Support\Facades\DB::table('users')->updateOrInsert(
                    ['username' => $adminUsername],
                    [
                        'email'         => $adminEmail,
                        'password_hash' => $hashedPassword,
                        'role_id'       => 1,
                        'is_active'     => 1,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]
                );
            }

            // 3.3 Crear usuario por defecto de tienda / cliente
            try {
                User::updateOrCreate(
                    ['username' => 'tienda'],
                    [
                        'email'         => "tienda@{$data['tenant_id']}.com",
                        'password_hash' => \Illuminate\Support\Facades\Hash::make('tienda123'),
                        'role_id'       => 2,
                        'is_active'     => true,
                        'token_login'   => null,
                    ]
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Error creando usuario tienda en tenant {$data['tenant_id']}: " . $e->getMessage());
            }

            // 3.4 Ejecutar seeders adicionales opcionales
            try {
                if (class_exists(\Database\Seeders\CourtSeeder::class)) {
                    app(\Database\Seeders\CourtSeeder::class)->run();
                }
            } catch (\Throwable $e) {}

            try {
                if (class_exists(\Database\Seeders\TelegramCommandSeeder::class)) {
                    app(\Database\Seeders\TelegramCommandSeeder::class)->run();
                }
            } catch (\Throwable $e) {}

            // 3.5 Aplicar preset de vertical de negocio seleccionado (o farmacia por defecto)
            try {
                $vertical = $data['vertical'] ?? \App\Enums\BusinessVerticalEnum::PHARMACY->value;
                app(\App\Contracts\VerticalPresetServiceInterface::class)->applyPreset($vertical, true);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Error aplicando preset de vertical en tenant {$data['tenant_id']}: " . $e->getMessage());
            }
        });

        // 4. Sincronizar datos maestros del central (orígenes, categorías, laboratorios) hacia el nuevo tenant
        try {
            $this->originSyncService->syncTenant($tenant);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Error sincronizando orígenes en nuevo tenant {$data['tenant_id']}: " . $e->getMessage());
        }

        try {
            $this->categorySyncService->syncTenant($tenant);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Error sincronizando categorías en nuevo tenant {$data['tenant_id']}: " . $e->getMessage());
        }

        try {
            $this->laboratorySyncService->syncTenant($tenant);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Error sincronizando laboratorios en nuevo tenant {$data['tenant_id']}: " . $e->getMessage());
        }

        try {
            $this->groupSyncService->syncTenant($tenant);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Error sincronizando grupos en nuevo tenant {$data['tenant_id']}: " . $e->getMessage());
        }

        try {
            $this->supplierSyncService->syncTenant($tenant);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Error sincronizando proveedores en nuevo tenant {$data['tenant_id']}: " . $e->getMessage());
        }

        return $tenant;
    }
}
