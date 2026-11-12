<?php

namespace Tests\Feature\Inventory;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LotManagementTest extends TestCase
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

    public function test_can_query_product_lots_and_available_stock(): void
    {
        $product = Product::create([
            'name' => 'Ibuprofeno 400mg',
            'code' => 'IBU-400',
            'price' => 4.00,
            'cost' => 2.00,
            'stock' => 25,
            'status' => 1,
        ]);

        $this->getJson('/api/product-lots')
            ->assertOk();

        $this->getJson('/api/product-lots/without-location')
            ->assertOk();

        $this->getJson("/api/lots/available-stock/{$product->id}")
            ->assertOk();

        $this->getJson("/api/lots/product/{$product->id}")
            ->assertOk();
    }
}
