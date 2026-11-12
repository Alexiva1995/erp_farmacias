<?php

namespace Tests\Feature\Bi;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryCyclicReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

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

        $this->user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_can_fetch_inventory_cyclic_report(): void
    {
        Product::create([
            'name' => 'Amoxicilina 500mg',
            'code' => 'AMO-500',
            'price' => 6.00,
            'cost' => 3.00,
            'stock' => 50,
            'status' => 1,
        ]);

        $today = now()->toDateString();

        $this->getJson("/api/bi/inventory-cyclic?start_date={$today}&end_date={$today}")
            ->assertOk();
    }
}
