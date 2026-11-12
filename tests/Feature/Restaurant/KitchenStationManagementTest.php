<?php

namespace Tests\Feature\Restaurant;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KitchenStationManagementTest extends TestCase
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

    public function test_can_list_and_create_kitchen_station(): void
    {
        $this->getJson('/api/kitchen-stations')
            ->assertOk();

        $createResponse = $this->postJson('/api/kitchen-stations', [
            'name' => 'Cocina Fría',
            'code' => 'CF-01',
            'is_active' => true,
        ]);

        $createResponse->assertSuccessful();
    }
}
