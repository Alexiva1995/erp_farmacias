<?php

declare(strict_types=1);

namespace Tests\Feature\Wholesale;

use App\Enums\B2BOrderStatusEnum;
use App\Models\Client;
use App\Models\DeliveryRoute;
use App\Models\Laboratory;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\User;
use App\Services\Wholesale\WholesaleOrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WmsDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_wms_workflow_endpoints_run_successfully(): void
    {
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user);

        $route = DeliveryRoute::create([
            'code' => 'RUTA-01',
            'name' => 'Ruta Centro Barquisimeto',
            'zone_state' => 'Lara',
            'is_active' => true,
        ]);

        $client = Client::create([
            'name' => 'Farmacia San Rafael',
            'identification' => '123456789',
            'identification_type' => 'J-',
            'delivery_route_id' => $route->id,
            'credit_limit_usd' => 5000.0,
            'default_credit_days' => 15,
            'sales_rep_id' => $user->id,
        ]);

        $lab = Laboratory::create([
            'name' => 'Laboratorios Leti',
            'default_credit_days' => 15,
        ]);

        $product = Product::create([
            'name' => 'Ibuprofeno 400mg',
            'barcode' => '7591234567890',
            'sale_price' => 5.0,
            'unit_cost' => 3.0,
            'stock' => 100,
            'laboratory_id' => $lab->id,
        ]);

        $lot = ProductLot::create([
            'product_id' => $product->id,
            'lot_number' => 'LOT-2026-A',
            'expiration_date' => Carbon::now()->addYear(),
            'quantity' => 100,
            'unit_cost' => 3.0,
        ]);

        $orderService = app(WholesaleOrderService::class);
        $result = $orderService->createWholesaleOrder([
            'client_id' => $client->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
            'notes' => 'Test orden WMS',
        ]);

        $orderId = (int) $result['orders'][0]['order_id'];
        Order::where('id', $orderId)->update([
            'b2b_workflow_status' => B2BOrderStatusEnum::APPROVED_CREDIT->value,
            'delivery_route_id' => $route->id,
        ]);

        // 1. Listar rutas
        $routesResponse = $this->getJson('/api/wholesale/wms/routes');
        $routesResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');

        // 2. Listar órdenes WMS
        $ordersResponse = $this->getJson('/api/wholesale/wms/orders');
        $ordersResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.0.id', $orderId)
            ->assertJsonPath('data.data.0.b2b_workflow_status', 'approved_credit');

        $order = Order::with('details')->find($orderId);
        $detailId = $order->details->first()->id;

        // 3. Iniciar Picking
        $startPickingResponse = $this->postJson("/api/wholesale/wms/orders/{$orderId}/start-picking");
        $startPickingResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.b2b_workflow_status', 'picking');

        // 4. Completar Picking
        $completePickingResponse = $this->postJson("/api/wholesale/wms/orders/{$orderId}/complete-picking", [
            'items' => [
                [
                    'order_detail_id' => $detailId,
                    'picked_quantity' => 5,
                ],
            ],
        ]);
        $completePickingResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.b2b_workflow_status', 'checking');

        // 5. Completar Packing (doble chequeo y bultos)
        $completePackingResponse = $this->postJson("/api/wholesale/wms/orders/{$orderId}/complete-packing", [
            'packages_count' => 2,
            'cold_boxes_count' => 1,
            'items' => [
                [
                    'order_detail_id' => $detailId,
                    'checked_quantity' => 5,
                ],
            ],
        ]);
        $completePackingResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.b2b_workflow_status', 'packed')
            ->assertJsonPath('data.packages_count', 2)
            ->assertJsonPath('data.cold_boxes_count', 1);

        // 6. Despachar orden
        $dispatchResponse = $this->postJson("/api/wholesale/wms/orders/{$orderId}/dispatch", [
            'delivery_route_id' => $route->id,
        ]);
        $dispatchResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.b2b_workflow_status', 'dispatched');
    }
}
