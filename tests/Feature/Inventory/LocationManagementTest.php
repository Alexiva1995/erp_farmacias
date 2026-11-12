<?php

namespace Tests\Feature\Inventory;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocationManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Admin',
                'guard_name' => 'web',
                'menu_permissions' => ['*'],
            ]
        );

        $this->admin = User::factory()->create([
            'role_id' => $role->id,
        ]);

        Sanctum::actingAs($this->admin);
    }

    public function test_can_list_create_and_delete_location(): void
    {
        $this->getJson('/api/locations')
            ->assertOk();

        $createResponse = $this->postJson('/api/locations', [
            'name' => 'Estante A1',
            'description' => 'Pasillo principal',
        ]);

        $createResponse->assertSuccessful();

        $locationId = $createResponse->json('data.id') ?? $createResponse->json('id');

        if ($locationId) {
            $this->deleteJson("/api/locations/{$locationId}")
                ->assertSuccessful();
        }
    }
}
