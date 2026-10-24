<?php

declare(strict_types=1);

namespace Tests\Feature\Restaurant;

use App\Enums\KitchenStatusEnum;
use App\Models\Client;
use App\Models\Dish;
use App\Models\KitchenStation;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2KdsTest extends TestCase
{
    protected User $user;
    protected KitchenStation $station;
    protected Dish $dish;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->station = KitchenStation::create([
            'name' => 'Cocina Caliente Test',
            'code' => 'HOT_TEST',
            'color' => 'error',
            'icon' => 'tabler-flame',
            'is_active' => true,
        ]);

        $this->dish = Dish::create([
            'name' => 'Hamburguesa KDS Test',
            'cost_price' => 3.00,
            'cpv' => 3.00,
            'suggested_price' => 6.00,
            'designated_price' => 8.00,
            'percentage_profit' => 2.0,
            'preparation_time_minutes' => 15,
            'kitchen_station_id' => $this->station->id,
            'status' => 1,
        ]);
    }

    public function test_can_list_kitchen_stations(): void
    {
        $response = $this->getJson('/api/kitchen-stations');

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Cocina Caliente Test', 'code' => 'HOT_TEST']);
    }

    public function test_can_send_order_to_kitchen(): void
    {
        $order = Order::create([
            'client_id' => Client::factory()->create()->id,
            'seller_id' => $this->user->id,
            'order_date' => now(),
            'status' => Order::PENDING,
            'currency' => 'USD',
        ]);

        $detail = OrderDetail::create([
            'order_id' => $order->id,
            'dish_id' => $this->dish->id,
            'product_type' => 'dish',
            'quantity' => 2,
            'price' => 8.00,
            'unit_price_usd' => 8.00,
            'unit_cost' => 3.00,
            'kitchen_status' => KitchenStatusEnum::DRAFT->value,
        ]);

        $response = $this->postJson("/api/kds/orders/{$order->id}/send");

        $response->assertOk()
            ->assertJsonFragment(['commanded_count' => 1]);

        $this->assertDatabaseHas('order_details', [
            'id' => $detail->id,
            'kitchen_status' => KitchenStatusEnum::QUEUED->value,
        ]);
    }

    public function test_can_update_item_kitchen_status_and_bump_ticket(): void
    {
        $order = Order::create([
            'client_id' => Client::factory()->create()->id,
            'seller_id' => $this->user->id,
            'order_date' => now(),
            'status' => Order::PENDING,
            'currency' => 'USD',
        ]);

        $detail = OrderDetail::create([
            'order_id' => $order->id,
            'dish_id' => $this->dish->id,
            'product_type' => 'dish',
            'quantity' => 1,
            'price' => 8.00,
            'unit_price_usd' => 8.00,
            'unit_cost' => 3.00,
            'kitchen_status' => KitchenStatusEnum::QUEUED->value,
            'commanded_at' => now(),
        ]);

        // Cambiar a preparando
        $response = $this->patchJson("/api/kds/items/{$detail->id}/status", [
            'status' => KitchenStatusEnum::PREPARING->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.kitchen_status', KitchenStatusEnum::PREPARING->value);

        // Despachar comanda (Bump)
        $bumpResponse = $this->postJson("/api/kds/orders/{$order->id}/bump");

        $bumpResponse->assertOk();

        $this->assertDatabaseHas('order_details', [
            'id' => $detail->id,
            'kitchen_status' => KitchenStatusEnum::READY->value,
        ]);
    }
}
