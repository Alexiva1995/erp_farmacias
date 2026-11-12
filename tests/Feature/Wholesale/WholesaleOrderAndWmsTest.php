<?php

declare(strict_types=1);

namespace Tests\Feature\Wholesale;

use App\Enums\B2BOrderStatusEnum;
use App\Models\Category;
use App\Models\Client;
use App\Models\DeliveryRoute;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WholesaleOrderAndWmsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Client $client;
    private Product $product;
    private DeliveryRoute $route;

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

        $this->route = DeliveryRoute::create([
            'code' => 'RUTA-VAL-01',
            'name' => 'Ruta Valencia Norte',
            'zone_state' => 'Carabobo',
            'driver_name' => 'Pedro Carreño',
            'vehicle_plate' => 'A12BC3D',
            'is_active' => true,
        ]);

        $this->client = Client::create([
            'name' => 'Farmacia San Juan C.A.',
            'identification' => 'J-309998881',
            'delivery_route_id' => $this->route->id,
            'credit_limit' => 1000.00,
            'credit_days' => 15,
            'balance' => 0.00,
        ]);

        $category = Category::firstOrCreate(['id' => 1], ['name' => 'Medicamentos B2B', 'type' => 'inventory']);

        $this->product = Product::create([
            'name' => 'Cefalexina 500mg Genérico',
            'barcode' => '7591009001',
            'category_id' => $category->id,
            'product_cost' => 2.00,
            'total_cost' => 2.00,
            'price' => 4.00,
            'price_usd' => 4.00,
            'sale_price' => 4.00,
            'stock' => 100,
            'type' => 'product',
            'status' => 'Active',
            'is_active' => true,
            'iva' => 0,
        ]);

        ProductLot::create([
            'product_id' => $this->product->id,
            'lot_number' => 'LOT-WMS-01',
            'quantity' => 100,
            'expiration_date' => now()->addMonths(18),
            'unit_cost' => 2.00,
        ]);

        $this->actingAs($this->admin);
    }

    public function test_can_create_wholesale_b2b_order(): void
    {
        $payload = [
            'client_id' => $this->client->id,
            'delivery_route_id' => $this->route->id,
            'notes' => 'Despachar en horario de la mañana',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                ]
            ],
        ];

        $response = $this->postJson('/api/wholesale/orders', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.orders_count', 1);

        $this->assertDatabaseHas('orders', [
            'client_id' => $this->client->id,
            'total_amount' => 40.00,
        ]);
    }

    public function test_can_approve_credit_for_pending_wholesale_order(): void
    {
        $order = Order::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->admin->id,
            'total_amount' => 150.00,
            'total_amount_usd' => 150.00,
            'currency' => 'USD',
            'order_date' => now(),
            'status' => Order::PENDING,
            'b2b_workflow_status' => B2BOrderStatusEnum::PENDING_CREDIT->value,
            'money_returns' => 0,
        ]);

        $response = $this->postJson("/api/wholesale/orders/{$order->id}/approve-credit");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'b2b_workflow_status' => B2BOrderStatusEnum::APPROVED_CREDIT->value,
        ]);
    }

    public function test_can_process_wms_picking_and_packing_flow(): void
    {
        $order = Order::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->admin->id,
            'delivery_route_id' => $this->route->id,
            'total_amount' => 80.00,
            'total_amount_usd' => 80.00,
            'currency' => 'USD',
            'order_date' => now(),
            'status' => Order::PENDING,
            'b2b_workflow_status' => B2BOrderStatusEnum::APPROVED_CREDIT->value,
            'money_returns' => 0,
        ]);

        $detail = OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'price' => 4.00,
            'unit_price_usd' => 4.00,
            'unit_cost' => 2.00,
            'product_type' => 'product',
            'total' => 80.00,
        ]);

        // 1. Iniciar Picking
        $startResponse = $this->postJson("/api/wholesale/wms/orders/{$order->id}/start-picking");

        $startResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'b2b_workflow_status' => B2BOrderStatusEnum::PICKING->value,
        ]);

        // 2. Completar Picking
        $completePickingResponse = $this->postJson("/api/wholesale/wms/orders/{$order->id}/complete-picking", [
            'items' => [
                [
                    'order_detail_id' => $detail->id,
                    'picked_quantity' => 20,
                ]
            ],
        ]);

        $completePickingResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'b2b_workflow_status' => B2BOrderStatusEnum::CHECKING->value,
        ]);

        // 3. Completar Doble Chequeo y Empaque (Packing)
        $completePackingResponse = $this->postJson("/api/wholesale/wms/orders/{$order->id}/complete-packing", [
            'packages_count' => 2,
            'cold_boxes_count' => 0,
            'items' => [
                [
                    'order_detail_id' => $detail->id,
                    'checked_quantity' => 20,
                ]
            ],
        ]);

        $completePackingResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'b2b_workflow_status' => B2BOrderStatusEnum::PACKED->value,
            'packages_count' => 2,
        ]);
    }
}
