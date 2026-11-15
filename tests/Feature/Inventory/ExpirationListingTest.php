<?php

namespace Tests\Feature\Inventory;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpirationListingTest extends TestCase
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

    public function test_can_list_expirations_and_expired_logs(): void
    {
        $this->getJson('/api/products/expirations')
            ->assertOk();

        $this->getJson('/api/expired-logs/summary')
            ->assertOk();

        $this->getJson('/api/expired-logs')
            ->assertOk();
    }
}
