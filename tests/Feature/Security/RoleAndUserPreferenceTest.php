<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAndUserPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::firstOrCreate(['id' => 1], [
            'name' => 'SuperAdmin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->adminUser = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->adminUser, 'sanctum');
    }

    public function test_can_list_roles_and_get_details(): void
    {
        $roleCashier = Role::create([
            'name' => 'Cajero Turno Diurno',
            'guard_name' => 'web',
            'menu_permissions' => ['pos', 'cash_closure'],
        ]);

        // 1. Listado vía /api/roles (ResourceCollection)
        $responseIndex = $this->getJson('/api/roles');
        $responseIndex->assertOk();
        $this->assertNotEmpty($responseIndex->json('data'));

        // 2. Listado vía /api/roles/list (ApiResponse::success)
        $responseList = $this->getJson('/api/roles/list');
        $responseList->assertOk()
            ->assertJsonPath('status', 'success');

        // 3. Detalle de rol
        $responseShow = $this->getJson("/api/roles/{$roleCashier->id}");
        $responseShow->assertOk()
            ->assertJsonPath('data.id', $roleCashier->id)
            ->assertJsonPath('data.name', 'Cajero Turno Diurno')
            ->assertJsonPath('data.menu_permissions', ['pos', 'cash_closure']);
    }

    public function test_can_create_and_update_role(): void
    {
        $createPayload = [
            'name' => 'Auditor de Inventario',
            'menu_permissions' => ['inventory', 'adjustments', 'cycle_counts'],
        ];

        $createResponse = $this->postJson('/api/roles', $createPayload);

        $createResponse->assertCreated()
            ->assertJsonPath('message', 'Rol creado exitosamente.')
            ->assertJsonPath('data.name', 'Auditor de Inventario');

        $roleId = (int) $createResponse->json('data.id');

        // Actualizar nombre
        $updatePayload = [
            'name' => 'Auditor Senior de Inventario',
        ];

        $updateResponse = $this->putJson("/api/roles/{$roleId}", $updatePayload);
        $updateResponse->assertOk()
            ->assertJsonPath('message', 'Rol actualizado exitosamente.')
            ->assertJsonPath('data.name', 'Auditor Senior de Inventario');

        // Actualizar específicamente permisos de menú
        $permissionsPayload = [
            'menu_permissions' => ['inventory', 'adjustments', 'cycle_counts', 'reports'],
        ];

        $permissionsResponse = $this->putJson("/api/roles/{$roleId}/menu-permissions", $permissionsPayload);
        $permissionsResponse->assertOk()
            ->assertJsonPath('message', 'Permisos de menú actualizados exitosamente.')
            ->assertJsonPath('data.menu_permissions', ['inventory', 'adjustments', 'cycle_counts', 'reports']);
    }

    public function test_role_deletion_integrity_protection(): void
    {
        // 1. Intentar borrar un rol que tiene usuarios asignados debe ser rechazado
        $deleteAdminRoleResponse = $this->deleteJson("/api/roles/{$this->adminRole->id}");
        $deleteAdminRoleResponse->assertStatus(422);

        $this->assertDatabaseHas('roles', ['id' => $this->adminRole->id]);

        // 2. Rol huérfano sin usuarios sí puede eliminarse
        $emptyRole = Role::create([
            'name' => 'Rol Temporal Inactivo',
            'guard_name' => 'web',
            'menu_permissions' => [],
        ]);

        $deleteEmptyResponse = $this->deleteJson("/api/roles/{$emptyRole->id}");
        $deleteEmptyResponse->assertOk()
            ->assertJsonPath('message', 'Rol eliminado exitosamente.');

        $this->assertDatabaseMissing('roles', ['id' => $emptyRole->id]);
    }

    public function test_user_ui_preferences_get_and_update(): void
    {
        // 1. Obtener preferencias iniciales
        $getResponse = $this->getJson('/api/user/ui-preferences');
        $getResponse->assertOk()
            ->assertJsonPath('status', 'success');

        // 2. Actualizar una preferencia
        $updatePayload = [
            'key' => 'dashboard_theme_mode',
            'value' => 'dark',
        ];

        $updateResponse = $this->postJson('/api/user/ui-preferences', $updatePayload);
        $updateResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.ui_preferences.dashboard_theme_mode', 'dark');

        // 3. Agregar otra preferencia
        $updatePayload2 = [
            'key' => 'sidebar_collapsed',
            'value' => true,
        ];

        $updateResponse2 = $this->postJson('/api/user/ui-preferences', $updatePayload2);
        $updateResponse2->assertOk()
            ->assertJsonPath('data.ui_preferences.dashboard_theme_mode', 'dark')
            ->assertJsonPath('data.ui_preferences.sidebar_collapsed', true);

        // 4. Verificar persistencia en base de datos
        $this->adminUser->refresh();
        $this->assertEquals('dark', $this->adminUser->ui_preferences['dashboard_theme_mode']);
        $this->assertTrue($this->adminUser->ui_preferences['sidebar_collapsed']);
    }
}
