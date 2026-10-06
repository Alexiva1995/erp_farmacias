<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TenantProvisioningService
{
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
            'plan_id' => $data['plan_id'] ?? null,
        ]);

        // 2. Asociar el dominio / subdominio
        $domain = $data['domain'];
        if (!str_contains($domain, '.')) {
            $domain = "{$data['domain']}.tovaerp.com";
        }

        $tenant->domains()->create([
            'domain' => $domain,
        ]);

        // 3. Sembrar datos maestros y configurar usuario administrador dentro del contexto del Tenant
        $tenant->run(function () use ($data) {
            // Ejecutar el DatabaseSeeder consolidado (roles, catálogo de proveedores, conexiones, telegram)
            if (class_exists(\Database\Seeders\DatabaseSeeder::class)) {
                (new \Database\Seeders\DatabaseSeeder())->run();
            }

            // Si se suministraron credenciales personalizadas para el Administrador, crearlo o actualizarlo
            if (!empty($data['admin_email']) && !empty($data['password'])) {
                User::updateOrCreate(
                    ['role_id' => 1],
                    [
                        'username' => $data['admin_name'] ?? 'admin',
                        'email' => $data['admin_email'],
                        'password_hash' => $data['password'],
                        'is_active' => true,
                    ]
                );
            }
        });

        return $tenant;
    }
}
