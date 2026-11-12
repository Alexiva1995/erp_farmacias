<?php

declare(strict_types=1);

namespace Tests\Feature\POS;

use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\ProductPack;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProductPackManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $productA;
    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('resources.all_exchange_rates');

        ExchangeRate::create(['currency_code' => 'USD', 'rate' => 1.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'BS', 'rate' => 36.0, 'source' => 'test']);

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->admin = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $category = Category::firstOrCreate(['id' => 1], ['name' => 'Farmacia', 'type' => 'inventory']);

        $this->productA = Product::create([
            'name' => 'Vitamina C 500mg',
            'barcode' => '7591003001',
            'category_id' => $category->id,
            'product_cost' => 2.00,
            'total_cost' => 2.00,
            'price' => 5.00,
            'price_usd' => 5.00,
            'stock' => 100,
            'type' => 'product',
            'status' => 'Active',
            'is_active' => true,
            'iva' => 0,
        ]);

        $this->productB = Product::create([
            'name' => 'Zinc 50mg',
            'barcode' => '7591003002',
            'category_id' => $category->id,
            'product_cost' => 1.50,
            'total_cost' => 1.50,
            'price' => 4.00,
            'price_usd' => 4.00,
            'stock' => 80,
            'type' => 'product',
            'status' => 'Active',
            'is_active' => true,
            'iva' => 0,
        ]);

        $this->actingAs($this->admin);
    }

    public function test_can_create_and_list_product_packs(): void
    {
        $payload = [
            'name' => 'Combo Antigripal Inmune',
            'total_price' => 7.50,
            'max_quantity' => 50,
            'is_active' => true,
            'pack_config' => [
                (string) $this->productA->id => [
                    'quantity' => 1,
                    'discount_percentage' => 10.0,
                    'sale_price' => 4.50,
                ],
                (string) $this->productB->id => [
                    'quantity' => 1,
                    'discount_percentage' => 25.0,
                    'sale_price' => 3.00,
                ]
            ],
        ];

        $storeResponse = $this->postJson('/api/tpv/promotions/product-packs', $payload);

        $storeResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Combo Antigripal Inmune');

        $this->assertDatabaseHas('product_packs', [
            'name' => 'Combo Antigripal Inmune',
            'total_price' => 7.50,
            'is_active' => 1,
        ]);

        $listResponse = $this->getJson('/api/tpv/promotions/product-packs');

        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 1);

        $this->assertCount(1, $listResponse->json('data'));
    }

    public function test_can_show_and_update_product_pack(): void
    {
        $pack = ProductPack::create([
            'name' => 'Combo Inicial',
            'total_price' => 8.00,
            'is_active' => true,
            'pack_config' => [
                (string) $this->productA->id => [
                    'quantity' => 1,
                    'discount_percentage' => 0,
                    'sale_price' => 5.00,
                ]
            ],
        ]);

        $showResponse = $this->getJson("/api/tpv/promotions/product-packs/{$pack->id}");

        $showResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $pack->id);

        $updatePayload = [
            'name' => 'Combo Inicial Renovado',
            'total_price' => 6.99,
            'is_active' => true,
            'pack_config' => [
                (string) $this->productA->id => [
                    'quantity' => 1,
                    'discount_percentage' => 15.0,
                    'sale_price' => 4.25,
                ]
            ],
        ];

        $updateResponse = $this->putJson("/api/tpv/promotions/product-packs/{$pack->id}", $updatePayload);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Combo Inicial Renovado');

        $this->assertDatabaseHas('product_packs', [
            'id' => $pack->id,
            'name' => 'Combo Inicial Renovado',
            'total_price' => 6.99,
        ]);
    }

    public function test_can_delete_product_pack(): void
    {
        $pack = ProductPack::create([
            'name' => 'Combo a Eliminar',
            'total_price' => 5.00,
            'is_active' => true,
            'pack_config' => [
                (string) $this->productA->id => [
                    'quantity' => 1,
                    'discount_percentage' => 0,
                    'sale_price' => 5.00,
                ]
            ],
        ]);

        $deleteResponse = $this->deleteJson("/api/tpv/promotions/product-packs/{$pack->id}");

        $deleteResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('product_packs', [
            'id' => $pack->id,
        ]);
    }
}
