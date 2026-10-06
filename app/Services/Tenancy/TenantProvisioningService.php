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
            // 3.1 Sembrar roles primero
            try {
                if (class_exists(\Database\Seeders\RolesSeeder::class)) {
                    app(\Database\Seeders\RolesSeeder::class)->run();
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Error sembrando roles en tenant {$data['tenant_id']}: " . $e->getMessage());
            }

            // 3.2 Crear o actualizar usuario Administrador principal
            $adminUsername = !empty($data['admin_name']) ? $data['admin_name'] : 'admin';
            $adminEmail = !empty($data['admin_email']) ? $data['admin_email'] : 'admin@tovaerp.com';
            $password = !empty($data['password']) ? $data['password'] : '12345678';

            User::updateOrCreate(
                ['username' => $adminUsername],
                [
                    'email'         => $adminEmail,
                    'password_hash' => $password,
                    'role_id'       => 1, // Rol Admin
                    'is_active'     => true,
                    'token_login'   => null,
                ]
            );

            // 3.3 Crear usuario por defecto de tienda / cliente
            try {
                User::firstOrCreate(
                    ['username' => 'tienda'],
                    [
                        'email'         => "tienda@{$data['tenant_id']}.com",
                        'password_hash' => 'tienda123',
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
        });

        return $tenant;
    }
}
