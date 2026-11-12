<?php

declare(strict_types=1);

namespace Tests\Feature\Ecommerce;

use App\Models\Category;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EcommercePublicAndAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $product;
    private Category $category;

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

        $this->category = Category::firstOrCreate(['id' => 1], ['name' => 'Cuidado Personal', 'type' => 'inventory']);

        $this->product = Product::create([
            'name' => 'Shampoo Anticaspa 400ml',
            'barcode' => '7591004001',
            'category_id' => $this->category->id,
            'product_cost' => 3.00,
            'total_cost' => 3.00,
            'price' => 6.00,
            'price_usd' => 6.00,
            'sale_price' => 6.00,
            'stock' => 50,
            'type' => 'product',
            'status' => 'Active',
            'is_active' => true,
            'is_deleted' => false,
            'is_favorite' => false,
            'iva' => 0,
        ]);

        ProductLot::create([
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-ECOMM-01',
            'quantity' => 50,
            'expiration_date' => now()->addMonths(12),
            'unit_cost' => 3.00,
        ]);
    }

    public function test_can_get_public_ecommerce_categories_and_products(): void
    {
        $categoriesResponse = $this->getJson('/api/public/ecommerce/categories');

        $categoriesResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $productsResponse = $this->getJson('/api/public/ecommerce/products');

        $productsResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $items = $productsResponse->json('data.data');
        $this->assertNotEmpty($items);
        $this->assertSame($this->product->id, $items[0]['id']);
    }

    public function test_can_toggle_favorite_status(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson("/api/public/ecommerce/products/{$this->product->id}/toggle-favorite");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_favorite', true);

        $this->assertDatabaseHas('products', [
            'id' => $this->product->id,
            'is_favorite' => 1,
        ]);
    }

    public function test_can_perform_ecommerce_checkout(): void
    {
        $payload = [
            'customer_name' => 'Valeria Morales',
            'customer_email' => 'valeria@example.com',
            'customer_phone' => '04129998877',
            'customer_document_type' => 'V',
            'customer_document_number' => '24555666',
            'shipping_address' => 'Urbanización Los Cedros Calle 3 Casa 12',
            'notes' => 'Entregar en portería',
            'payment_method' => 'pago_movil',
            'payment_currency' => 'USD',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                ]
            ],
        ];

        $response = $this->postJson('/api/public/ecommerce/checkout', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertEquals(12.0, $response->json('total_amount'));

        $this->assertDatabaseHas('ecommerce_orders', [
            'customer_name' => 'Valeria Morales',
            'total_amount' => 12.00,
        ]);

        $this->assertDatabaseHas('ecommerce_order_items', [
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 6.00,
        ]);
    }

    public function test_admin_can_manage_ecommerce_orders(): void
    {
        $this->actingAs($this->admin);

        $orderId = DB::table('ecommerce_orders')->insertGetId([
            'customer_name' => 'Jorge Ramos',
            'customer_email' => 'jorge@example.com',
            'customer_phone' => '04141112233',
            'shipping_address' => 'Av. Bolívar Casa 5',
            'total_amount' => 18.00,
            'status' => 'Pending',
            'payment_method' => 'pago_movil',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $listResponse = $this->getJson('/api/ecommerce/admin/orders');

        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $approveResponse = $this->postJson("/api/ecommerce/admin/orders/{$orderId}/approve");

        $approveResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('ecommerce_orders', [
            'id' => $orderId,
            'status' => 'Paid',
        ]);
    }
}
