<?php

namespace Tests\Feature\POS;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosOrderListingTest extends TestCase
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

    public function test_can_list_pos_orders_by_status(): void
    {
        $this->getJson('/api/tpv/orders/all')
            ->assertOk();

        $this->getJson('/api/tpv/orders/completed')
            ->assertOk();

        $this->getJson('/api/tpv/orders/cancelled')
            ->assertOk();

        $this->getJson('/api/tpv/orders/reserved-list')
            ->assertOk();
    }
}
