<?php

namespace Tests\Feature\Purchases;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseOrderByLaboratoryListingTest extends TestCase
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

    public function test_can_fetch_purchase_orders_by_laboratory_and_stats(): void
    {
        $this->getJson('/api/suppliers/purchase-orders-laboratory/stats')
            ->assertOk();

        $this->getJson('/api/suppliers/purchase-orders-laboratory')
            ->assertOk();
    }
}
