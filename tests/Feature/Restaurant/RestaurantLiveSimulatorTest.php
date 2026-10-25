<?php

namespace Tests\Feature\Restaurant;

use App\Models\Category;
use App\Models\Client;
use App\Models\DiningTable;
use App\Models\DiningZone;
use App\Models\Dish;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantLiveSimulatorTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DiningZone $zone;
    protected DiningTable $table;
    protected Dish $dish;

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
            'name' => 'Salón Simulador',
            'background_style' => 'grid',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->table = DiningTable::create([
            'dining_zone_id' => $this->zone->id,
            'name' => 'Mesa SIM-01',
            'capacity' => 4,
            'pos_x' => 100,
            'pos_y' => 100,
            'status' => 'available',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Platos Demo',
            'status' => 'active',
        ]);

        Client::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Cliente Mostrador',
                'identification' => '00000000',
                'identification_type' => 'V-',
            ]
        );

        $this->dish = Dish::create([
            'name' => 'Pizza Margarita Simulación',
            'cost_price' => 3.00,
            'cpv' => 3.00,
            'suggested_price' => 10.00,
            'designated_price' => 10.00,
            'percentage_profit' => 30.00,
            'category_id' => $category->id,
            'status' => 1,
        ]);
    }

    public function test_can_get_simulator_status()
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/restaurant/simulator/status');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'total_tables',
                'occupied_tables',
                'kitchen' => ['queued', 'preparing', 'ready'],
            ]);
    }

    public function test_can_trigger_simulated_order()
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/restaurant/simulator/trigger-order');

        $res->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->table->refresh();
        $this->assertEquals('occupied', $this->table->status->value);
        $this->assertNotNull($this->table->current_order_id);
    }

    public function test_can_advance_kitchen_and_guest_action()
    {
        // 1. Crear una orden primero
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/restaurant/simulator/trigger-order');

        // 2. Avanzar estado en cocina (de queued a preparing)
        $kitchenRes = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/restaurant/simulator/advance-kitchen');

        $kitchenRes->assertStatus(200);

        // 3. Simular acción de comensal
        $guestRes = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/restaurant/simulator/trigger-guest');

        $guestRes->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_can_reset_simulation()
    {
        // 1. Crear orden
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/restaurant/simulator/trigger-order');

        // 2. Resetear
        $resetRes = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/restaurant/simulator/reset');

        $resetRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->table->refresh();
        $this->assertEquals('available', $this->table->status->value);
        $this->assertNull($this->table->current_order_id);
    }
}
