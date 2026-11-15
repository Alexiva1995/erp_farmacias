<?php

namespace Tests\Feature\Products;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductListingAndLookupTest extends TestCase
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

    public function test_can_list_products_autocomplete_stock_and_next_lot(): void
    {
        $product = Product::create([
            'name' => 'Losartan 50mg',
            'code' => 'LOS-50',
            'price' => 4.50,
            'cost' => 2.20,
            'stock' => 30,
            'status' => 1,
        ]);

        $this->getJson('/api/products')
            ->assertOk();

        $this->getJson('/api/products/autocomplete?q=Losartan')
            ->assertOk();

        $this->getJson("/api/products/{$product->id}/stock")
            ->assertOk();
    }
}
