<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Models\CleaningActivity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleaningActivityManagementTest extends TestCase
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

    public function test_can_create_and_list_cleaning_activities(): void
    {
        $payload = [
            'activity' => 'Desinfección de Mostradores y Neveras',
            'description' => 'Limpieza con alcohol al 70% al inicio del turno',
            'frequency' => 'Diaria',
        ];

        $storeResponse = $this->postJson('/api/cleaning-activities', $payload);

        $storeResponse->assertStatus(201)
            ->assertJsonPath('message', 'Actividad creada con éxito.')
            ->assertJsonPath('activity.activity', 'Desinfección de Mostradores y Neveras');

        $this->assertDatabaseHas('cleaning_activities', [
            'activity' => 'Desinfección de Mostradores y Neveras',
            'frequency' => 'Diaria',
        ]);

        $listResponse = $this->getJson('/api/cleaning-activities');

        $listResponse->assertStatus(200);
        $this->assertNotEmpty($listResponse->json('data'));
    }

    public function test_can_update_cleaning_activity(): void
    {
        $activity = CleaningActivity::create([
            'activity' => 'Limpieza de Pisos Principal',
            'description' => 'Barrer y trapear área de ventas',
            'frequency' => 'Diaria',
        ]);

        $updatePayload = [
            'activity' => 'Limpieza de Pisos y Pasillos',
            'frequency' => 'Semanal',
        ];

        $response = $this->putJson("/api/cleaning-activities/{$activity->id}", $updatePayload);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Actividad actualizada con éxito.')
            ->assertJsonPath('activity.activity', 'Limpieza de Pisos y Pasillos')
            ->assertJsonPath('activity.frequency', 'Semanal');

        $this->assertDatabaseHas('cleaning_activities', [
            'id' => $activity->id,
            'activity' => 'Limpieza de Pisos y Pasillos',
            'frequency' => 'Semanal',
        ]);
    }

    public function test_can_delete_cleaning_activity(): void
    {
        $activity = CleaningActivity::create([
            'activity' => 'Mantenimiento de Filtros Aire',
            'frequency' => 'Mensual',
        ]);

        $response = $this->deleteJson("/api/cleaning-activities/{$activity->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('cleaning_activities', [
            'id' => $activity->id,
        ]);
    }
}
