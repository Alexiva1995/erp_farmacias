<?php

declare(strict_types=1);

namespace Tests\Feature\POS;

use App\Models\Category;
use App\Models\Client;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ReturnEntry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SalesReturnManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Client $client;
    private Product $product;
    private Order $order;

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

        $this->product = Product::create([
            'name' => 'Amoxicilina 500mg',
            'barcode' => '7591002002',
            'category_id' => $category->id,
            'product_cost' => 2.00,
            'total_cost' => 2.00,
            'price' => 4.00,
            'price_usd' => 4.00,
            'stock' => 50,
            'type' => 'product',
            'status' => 'Active',
            'is_active' => true,
            'iva' => 0,
        ]);

        $this->client = Client::create([
            'name' => 'Manuel',
            'last_name' => 'Rondón',
            'identification' => 'V-22111333',
            'balance' => 0.00,
        ]);

        $this->order = Order::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->admin->id,
            'total_amount' => 20.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'order_date' => now(),
            'status' => Order::COMPLETED,
        ]);

        OrderDetail::create([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'price' => 4.00,
            'unit_price_usd' => 4.00,
            'unit_cost' => 2.00,
            'product_type' => 'product',
            'total' => 20.00,
        ]);

        $this->actingAs($this->admin);
    }

    public function test_can_search_orders_for_return_by_identification(): void
    {
        $response = $this->postJson('/api/tpv/returns/search-orders', [
            'identification' => 'V-22111333',
            'itemsPerPage' => 10,
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertSame($this->order->id, $data[0]['id']);
    }

    public function test_can_process_product_return(): void
    {
        $payload = [
            'order' => [
                'id' => $this->order->id,
                'currency' => 'USD',
                'usd_conversion' => 1.0,
                'client' => $this->client->toArray(),
                'details' => [
                    [
                        'product_id' => $this->product->id,
                        'unit_price_usd' => 4.00,
                        'discount_percentage' => 0,
                    ]
                ],
            ],
            'product' => [
                'id' => $this->product->id,
            ],
            'returns_quantity' => 2,
        ];

        $response = $this->postJson('/api/tpv/returns/product', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('returns', [
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'amount_refunded' => 8.00,
        ]);
    }

    public function test_can_list_returns_and_update_status(): void
    {
        $returnEntry = ReturnEntry::create([
            'order_id' => $this->order->id,
            'generated_by_id' => $this->admin->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'amount_refunded' => 4.00,
            'return_date' => now(),
            'status' => null,
        ]);

        $listResponse = $this->getJson('/api/tpv/returns?itemsPerPage=10');

        $listResponse->assertStatus(200)
            ->assertJsonPath('total', 1);

        $statusResponse = $this->patchJson("/api/tpv/returns/{$returnEntry->id}/Approved");

        $statusResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('returns', [
            'id' => $returnEntry->id,
            'status' => ReturnEntry::APPROVED,
        ]);

        $this->client->refresh();
        $this->assertEquals(4.00, $this->client->balance);
    }
}
