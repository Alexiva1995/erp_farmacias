<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\AutoReplenishmentConfig;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoReplenishmentConfigTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->admin = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin);
    }

    public function test_can_create_and_list_auto_replenishment_configs(): void
    {
        $payload = [
            'name' => 'Reposición Quincenal Farmacia Central',
            'is_active' => true,
            'tipo_filtracion' => 'average',
            'lapso_de_tiempo' => '15 days',
            'min_solicitar' => 5,
            'con_descuento' => true,
            'schedule_expression' => '0 8 * * 1',
        ];

        $storeResponse = $this->postJson('/api/auto-replenishment-configs', $payload);

        $storeResponse->assertStatus(201)
            ->assertJsonPath('data.name', 'Reposición Quincenal Farmacia Central');

        $this->assertDatabaseHas('auto_replenishment_configs', [
            'name' => 'Reposición Quincenal Farmacia Central',
            'tipo_filtracion' => 'average',
            'lapso_de_tiempo' => '15 days',
        ]);

        $listResponse = $this->getJson('/api/auto-replenishment-configs');

        $listResponse->assertStatus(200);
        $this->assertNotEmpty($listResponse->json('data'));
    }

    public function test_can_update_auto_replenishment_config(): void
    {
        $config = AutoReplenishmentConfig::create([
            'name' => 'Configuración Inicial',
            'is_active' => true,
            'tipo_filtracion' => 'sales',
            'lapso_de_tiempo' => '1 month',
            'min_solicitar' => 2,
            'con_descuento' => false,
            'schedule_expression' => '0 6 * * *',
        ]);

        $updatePayload = [
            'name' => 'Configuración Actualizada',
            'tipo_filtracion' => 'average',
            'lapso_de_tiempo' => '7 days',
            'min_solicitar' => 10,
            'schedule_expression' => '0 7 * * *',
        ];

        $response = $this->putJson("/api/auto-replenishment-configs/{$config->id}", $updatePayload);

        $response->assertStatus(200)
            ->assertJsonPath('name', 'Configuración Actualizada')
            ->assertJsonPath('lapso_de_tiempo', '7 days');

        $this->assertDatabaseHas('auto_replenishment_configs', [
            'id' => $config->id,
            'name' => 'Configuración Actualizada',
            'min_solicitar' => 10,
        ]);
    }

    public function test_can_delete_auto_replenishment_config(): void
    {
        $config = AutoReplenishmentConfig::create([
            'name' => 'Regla a Eliminar',
            'is_active' => false,
            'tipo_filtracion' => 'average',
            'lapso_de_tiempo' => '1 month',
            'min_solicitar' => 1,
            'schedule_expression' => '0 0 1 * *',
        ]);

        $response = $this->deleteJson("/api/auto-replenishment-configs/{$config->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Configuración eliminada.');

        $this->assertDatabaseMissing('auto_replenishment_configs', [
            'id' => $config->id,
        ]);
    }
}
