<?php

namespace Tests\Feature\Restaurant;

use App\Enums\DiningTableStatusEnum;
use App\Enums\TableShapeEnum;
use App\Models\Category;
use App\Models\Client;
use App\Models\DiningTable;
use App\Models\DiningZone;
use App\Models\Dish;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase5QrAndReservationsTest extends TestCase
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
            'name' => 'Salón Terraza',
            'background_style' => 'grid',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->table = DiningTable::create([
            'dining_zone_id' => $this->zone->id,
            'name' => 'Mesa QR 05',
            'capacity' => 4,
            'shape' => TableShapeEnum::SQUARE->value,
            'pos_x' => 100,
            'pos_y' => 100,
            'status' => DiningTableStatusEnum::AVAILABLE->value,
            'qr_token' => 'token_secreto_mesa_5',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Platos Fuertes',
            'status' => 'active',
        ]);

        Client::create([
            'name' => 'Consumidor QR',
            'identification' => '00000000',
            'identification_type' => 'V-',
        ]);

        $this->dish = Dish::create([
            'name' => 'Hamburguesa Clásica Gourmet',
            'suggested_price' => 12.00,
            'designated_price' => 12.00,
            'cost_price' => 4.00,
            'cpv' => 4.00,
            'percentage_profit' => 30.00,
            'category_id' => $category->id,
            'status' => 1,
        ]);
    }

    public function test_can_view_table_menu_and_submit_qr_order()
    {
        // 1. Consultar estado y menú de mesa por QR token
        $viewRes = $this->getJson("/api/public/restaurant/qr/table/{$this->table->qr_token}");
        $viewRes->assertStatus(200)
            ->assertJsonPath('table_name', 'Mesa QR 05')
            ->assertJsonPath('status', DiningTableStatusEnum::AVAILABLE->value);

        // 2. Enviar comanda desde el móvil del comensal
        $orderRes = $this->postJson("/api/public/restaurant/qr/table/{$this->table->qr_token}/order", [
            'guests_count' => 2,
            'items' => [
                [
                    'dish_id' => $this->dish->id,
                    'quantity' => 2,
                    'notes' => 'Sin pepinillos',
                    'seat_number' => 1,
                ]
            ]
        ]);

        $orderRes->assertStatus(201)
            ->assertJsonPath('total_usd', fn ($val) => (float) $val === 24.0);

        // La mesa queda en estado ocupada y con orden vinculada
        $this->table->refresh();
        $this->assertEquals(DiningTableStatusEnum::OCCUPIED, $this->table->status);
        $this->assertTrue((bool) $this->table->needs_attention);
    }

    public function test_can_request_assistance_and_call_waiter()
    {
        $response = $this->postJson("/api/public/restaurant/qr/table/{$this->table->qr_token}/assistance", [
            'reason' => 'waiter_call'
        ]);

        $response->assertStatus(200);

        $this->table->refresh();
        $this->assertTrue((bool) $this->table->needs_attention);
        $this->assertEquals('waiter_call', $this->table->attention_reason);
    }

    public function test_can_create_reservation_and_check_in()
    {
        $client = Client::create([
            'name' => 'Alejandro Morales',
            'identification' => '19887766',
            'identification_type' => 'V-',
        ]);

        // Crear Reserva
        $res = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/restaurant/reservations', [
                'dining_table_id' => $this->table->id,
                'client_name' => $client->name,
                'client_whatsapp' => '+584121234567',
                'date' => now()->toDateString(),
                'start_time' => '20:00',
                'guests_count' => 4,
                'special_requests' => 'Mesa preferencial',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('reservation.client_name', 'Alejandro Morales');

        $reservationId = $res->json('reservation.id');
        $reservation = Reservation::find($reservationId);

        // Check-in (sentar al cliente)
        $checkInRes = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/restaurant/reservations/{$reservation->id}/check-in");

        $checkInRes->assertStatus(200);

        $this->table->refresh();
        $this->assertEquals(DiningTableStatusEnum::OCCUPIED, $this->table->status);
        $this->assertNotNull($this->table->current_order_id);
    }
}
