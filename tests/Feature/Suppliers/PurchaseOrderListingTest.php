<?php

namespace Tests\Feature\Suppliers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseOrderListingTest extends TestCase
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

    public function test_can_list_purchase_orders_stats_and_history(): void
    {
        $this->getJson('/api/purchase-orders')
            ->assertOk();

        $this->getJson('/api/purchase-orders/stats')
            ->assertOk();

        $this->getJson('/api/purchase-orders/history')
            ->assertOk();
    }
}
