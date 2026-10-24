<?php

namespace Tests\Feature\Restaurant;

use App\Enums\DiningTableStatusEnum;
use App\Enums\TableShapeEnum;
use App\Models\Client;
use App\Models\DiningTable;
use App\Models\DiningZone;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3DiningAndSplitCheckTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DiningZone $zone;
    protected DiningTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'admin',
            'description' => 'Administrator',
        ]);

        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->zone = DiningZone::create([
            'name' => 'Terraza Principal',
            'background_style' => 'grid',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->table = DiningTable::create([
            'dining_zone_id' => $this->zone->id,
            'name' => 'Mesa 10',
            'capacity' => 4,
            'shape' => TableShapeEnum::SQUARE->value,
            'pos_x' => 150,
            'pos_y' => 150,
            'width' => 90,
            'height' => 90,
            'rotation' => 0,
            'status' => DiningTableStatusEnum::AVAILABLE->value,
            'is_active' => true,
        ]);
    }

    public function test_can_list_dining_overview()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/dining-floor/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'zones' => [
                    '*' => ['id', 'name', 'background_style', 'tables_count']
                ],
                'tables' => [
                    '*' => ['id', 'name', 'capacity', 'shape', 'pos_x', 'pos_y', 'status']
                ]
            ]);
    }

    public function test_can_open_table_order()
    {
        $client = Client::create([
            'name' => 'Juan Perez',
            'identification' => '12345678',
            'identification_type' => 'V-',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/dining-floor/tables/{$this->table->id}/open-order", [
                'guests_count' => 3,
                'client_id' => $client->id,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('dining_tables', [
            'id' => $this->table->id,
            'status' => DiningTableStatusEnum::OCCUPIED->value,
        ]);

        $this->assertDatabaseHas('orders', [
            'dining_table_id' => $this->table->id,
            'guests_count' => 3,
            'status' => Order::PENDING,
        ]);
    }

    public function test_can_generate_pre_bill_ticket()
    {
        $client = Client::create([
            'name' => 'Maria Lopez',
            'identification' => '87654321',
            'identification_type' => 'V-',
        ]);

        $order = Order::create([
            'client_id' => $client->id,
            'seller_id' => $this->user->id,
            'order_date' => now(),
            'status' => Order::PENDING,
            'currency' => 'USD',
            'total_amount' => 50.00,
            'total_amount_usd' => 50.00,
            'total_cost' => 20.00,
            'money_returns' => 0,
            'usd_conversion' => 1,
            'dining_table_id' => $this->table->id,
            'guests_count' => 2,
        ]);

        $this->table->update([
            'status' => DiningTableStatusEnum::OCCUPIED->value,
            'current_order_id' => $order->id,
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_type' => 'dish',
            'quantity' => 2,
            'price' => 25.00,
            'unit_price_usd' => 25.00,
            'unit_cost' => 10.00,
            'notes' => 'Corte especial',
            'seat_number' => 1,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/orders/{$order->id}/pre-bill", [
                'service_percentage' => 10.0,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('pre_bill.subtotal_usd', fn ($val) => (float) $val === 50.0)
            ->assertJsonPath('pre_bill.service_amount_usd', fn ($val) => (float) $val === 5.0)
            ->assertJsonPath('pre_bill.grand_total_usd', fn ($val) => (float) $val === 55.0);

        // Mesa cambia a status bill_requested
        $this->assertDatabaseHas('dining_tables', [
            'id' => $this->table->id,
            'status' => DiningTableStatusEnum::BILL_REQUESTED->value,
        ]);
    }

    public function test_can_split_order_equally()
    {
        $client = Client::create([
            'name' => 'Carlos Gomez',
            'identification' => '11223344',
            'identification_type' => 'V-',
        ]);

        $order = Order::create([
            'client_id' => $client->id,
            'seller_id' => $this->user->id,
            'order_date' => now(),
            'status' => Order::PENDING,
            'currency' => 'USD',
            'total_amount' => 100.00,
            'total_amount_usd' => 100.00,
            'total_cost' => 40.00,
            'money_returns' => 0,
            'usd_conversion' => 1,
            'dining_table_id' => $this->table->id,
            'guests_count' => 4,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/orders/{$order->id}/split", [
                'type' => 'equal',
                'parts' => 4,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('result.parts_count', 4)
            ->assertJsonPath('result.divisions.0.amount_usd', fn ($val) => (float) $val === 25.0);
    }

    public function test_can_split_order_by_items_and_seats()
    {
        $client = Client::create([
            'name' => 'Comensal Familiar',
            'identification' => '99887766',
            'identification_type' => 'V-',
        ]);

        $order = Order::create([
            'client_id' => $client->id,
            'seller_id' => $this->user->id,
            'order_date' => now(),
            'status' => Order::PENDING,
            'currency' => 'USD',
            'total_amount' => 40.00,
            'total_amount_usd' => 40.00,
            'total_cost' => 15.00,
            'money_returns' => 0,
            'usd_conversion' => 1,
            'dining_table_id' => $this->table->id,
        ]);

        $item1 = OrderDetail::create([
            'order_id' => $order->id,
            'product_type' => 'dish',
            'quantity' => 1,
            'price' => 20.00,
            'unit_price_usd' => 20.00,
            'unit_cost' => 8.00,
            'seat_number' => 1,
        ]);

        $item2 = OrderDetail::create([
            'order_id' => $order->id,
            'product_type' => 'dish',
            'quantity' => 1,
            'price' => 20.00,
            'unit_price_usd' => 20.00,
            'unit_cost' => 7.00,
            'seat_number' => 2,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/orders/{$order->id}/split", [
                'type' => 'seats_items',
                'splits' => [
                    ['seat_number' => 1, 'detail_ids' => [$item1->id]],
                    ['seat_number' => 2, 'detail_ids' => [$item2->id]],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('created_orders_count', 2);

        // Verificar que item2 ahora pertenece a una orden hija
        $this->assertDatabaseHas('orders', [
            'parent_order_id' => $order->id,
        ]);

        $item2->refresh();
        $this->assertNotEquals($order->id, $item2->order_id);
    }
}
