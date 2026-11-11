<?php

declare(strict_types=1);

namespace Tests\Feature\POS;

use App\Models\CashClosing;
use App\Models\Client;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PosAdvancedOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private Client $client;
    private Supplier $supplier;
    private Product $product;
    private CashClosing $openCash;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('resources.all_exchange_rates');

        ExchangeRate::create(['currency_code' => 'USD', 'rate' => 1.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'BS', 'rate' => 36.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'COP', 'rate' => 4000.0, 'source' => 'test']);

        DB::table('general_settings')->insert([
            'fiscal_mode' => 'demo',
            'special_taxpayer_status' => 'desactivada',
            'business_type' => 'retail',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->seller = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->seller);

        $this->client = Client::create([
            'identification' => '22333444',
            'identification_type' => Client::IDENTIFICATION_TYPE_VENEZOLANO,
            'name' => 'Andres',
            'last_name' => 'Bello',
            'phone' => '04147896541',
            'address' => 'Av. Universidad',
            'is_spe' => false,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Droguería Central',
            'rif' => 'J-40112233-1',
            'phone' => '02128889900',
        ]);

        $this->product = Product::create([
            'name' => 'Ibuprofeno 400mg',
            'unit_cost' => 1.00,
            'sale_price' => 2.00,
            'iva' => 0,
        ]);

        ProductLot::create([
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'lot_number' => 'LOT-ADV-001',
            'expiration_date' => now()->addYear()->format('Y-m-d'),
            'quantity' => 100,
            'unit_cost' => 1.00,
        ]);

        $this->openCash = CashClosing::create([
            'seller_id' => $this->seller->id,
            'status' => CashClosing::OPEN,
            'closing_date' => now(),
        ]);
    }

    public function test_can_consult_client_by_identification_at_tpv(): void
    {
        $response = $this->getJson("/api/tpv/order/client/{$this->client->identification}");

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'found' => true,
                'client' => [
                    'identification' => '22333444',
                    'name' => 'Andres',
                ],
            ],
        ]);
    }

    public function test_can_get_seller_open_order(): void
    {
        $createResponse = $this->postJson('/api/tpv/orders', [
            'seller_id' => $this->seller->id,
            'client_id' => $this->client->id,
            'currency' => 'USD',
        ]);
        $createResponse->assertStatus(201);

        $response = $this->getJson('/api/tpv/order/seller/my-open-order');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
        $this->assertNotNull($response->json('data.order.pending_order'));
        $this->assertEquals($this->seller->id, $response->json('data.order.pending_order.seller_id'));
    }

    public function test_can_reserve_and_list_reserved_orders(): void
    {
        $order = Order::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->seller->id,
            'cash_closing_id' => $this->openCash->id,
            'status' => Order::PENDING,
            'total_amount' => 10.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'total_amount_usd' => 10.00,
            'payment_methods' => json_encode([]),
        ]);

        $reserveResponse = $this->patchJson("/api/tpv/order/{$order->id}/reserve");
        $reserveResponse->assertStatus(200);

        $order->refresh();
        $this->assertEquals(Order::RESERVED, $order->status);

        $listResponse = $this->getJson('/api/tpv/orders/reserved-list');
        $listResponse->assertStatus(200);
        $this->assertNotEmpty($listResponse->json('orders'));
    }

    public function test_can_cancel_order(): void
    {
        $order = Order::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->seller->id,
            'cash_closing_id' => $this->openCash->id,
            'status' => Order::PENDING,
            'total_amount' => 10.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'total_amount_usd' => 10.00,
            'payment_methods' => json_encode([]),
        ]);

        $response = $this->patchJson("/api/tpv/orders/{$order->id}/cancelled");

        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals(Order::CANCELLED, $order->status);
    }

    public function test_can_abandon_order(): void
    {
        $order = Order::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->seller->id,
            'cash_closing_id' => $this->openCash->id,
            'status' => Order::PENDING,
            'total_amount' => 15.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'total_amount_usd' => 15.00,
            'payment_methods' => json_encode([]),
        ]);

        $response = $this->patchJson("/api/tpv/orders/{$order->id}/abandon");

        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals(Order::ABANDONED, $order->status);
    }

    public function test_can_remove_item_from_order(): void
    {
        $order = Order::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->seller->id,
            'cash_closing_id' => $this->openCash->id,
            'status' => Order::PENDING,
            'total_amount' => 4.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'total_amount_usd' => 4.00,
            'payment_methods' => json_encode([]),
        ]);

        $orderDetail = OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_type' => 'normal',
            'quantity' => 2,
            'price' => 2.00,
            'unit_cost' => 1.00,
            'total_cost' => 2.00,
        ]);

        $response = $this->deleteJson("/api/tpv/orders/{$order->id}/items/{$orderDetail->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('order_details', [
            'id' => $orderDetail->id,
        ]);
    }

    public function test_can_list_completed_and_all_orders(): void
    {
        Order::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->seller->id,
            'cash_closing_id' => $this->openCash->id,
            'status' => Order::COMPLETED,
            'total_amount' => 30.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'total_amount_usd' => 30.00,
            'payment_methods' => json_encode([['method' => 'USD_CASH', 'amount' => 30.00]]),
        ]);

        $completedResponse = $this->getJson('/api/tpv/orders/completed');
        $completedResponse->assertStatus(200);
        $completedResponse->assertJsonStructure(['data', 'total']);
        $this->assertGreaterThanOrEqual(1, $completedResponse->json('total'));

        $allResponse = $this->getJson('/api/tpv/orders/all');
        $allResponse->assertStatus(200);
        $allResponse->assertJsonStructure(['data', 'total']);
        $this->assertGreaterThanOrEqual(1, $allResponse->json('total'));
    }
}
