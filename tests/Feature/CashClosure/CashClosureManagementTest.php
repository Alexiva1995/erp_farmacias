<?php

declare(strict_types=1);

namespace Tests\Feature\CashClosure;

use App\Models\CashClosing;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CashClosureManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
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
            'blind_cash_closure' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->user);

        $this->openCash = CashClosing::create([
            'seller_id' => $this->user->id,
            'status' => CashClosing::OPEN,
            'closing_date' => now(),
            'total_sales' => 50.00,
            'total_cop' => 200000.00,
            'cop_cash' => 200000.00,
            'usd_cash' => 50.00,
            'total_usd' => 50.00,
        ]);
    }

    public function test_can_get_current_open_cash_closure(): void
    {
        $response = $this->getJson('/api/finances/cash-closure');

        $response->assertStatus(200);
        $this->assertEquals($this->openCash->id, $response->json('id'));
        $this->assertEquals(CashClosing::OPEN, $response->json('status'));
    }

    public function test_can_close_cash_closure(): void
    {
        $payload = [
            'id' => $this->openCash->id,
            'total_cop' => 200000,
            'sobrante_en_peso' => 0,
            'entregar_efectivo_cop' => 200000,
            'ticket_html' => '<div>Ticket de cierre de prueba</div>',
            'history_html' => '<div>Historial de prueba</div>',
            'is_blind' => false,
        ];

        $response = $this->postJson('/api/finances/cash-closure/close', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Caja cerrada exitosamente.',
        ]);

        $this->openCash->refresh();
        $this->assertEquals(CashClosing::CLOSED, $this->openCash->status);
    }

    public function test_can_close_blind_cash_closure_with_declared_amounts(): void
    {
        $payload = [
            'id' => $this->openCash->id,
            'total_cop' => 200000,
            'sobrante_en_peso' => 0,
            'entregar_efectivo_cop' => 200000,
            'ticket_html' => '<div>Ticket ciego</div>',
            'history_html' => '<div>Historial ciego</div>',
            'is_blind' => true,
            'declared_cop' => 205000,
            'declared_usd' => 50,
            'declared_cop_transfer' => 0,
            'declared_credit' => 0,
            'declared_bs_mobile' => 0,
            'declared_bs_card' => 0,
        ];

        $response = $this->postJson('/api/finances/cash-closure/close', $payload);

        $response->assertStatus(200);
        $this->openCash->refresh();

        $this->assertEquals(CashClosing::CLOSED, $this->openCash->status);
        $this->assertEquals(205000, (float) $this->openCash->declared_cop);
        $this->assertEquals(5000, (float) $this->openCash->cop_spare);
    }

    public function test_can_get_closing_history(): void
    {
        $this->openCash->update([
            'status' => CashClosing::CLOSED,
            'closing_date' => now(),
        ]);

        $response = $this->getJson('/api/finances/cash-closure/closingHistory');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'total',
        ]);
        $this->assertGreaterThanOrEqual(1, $response->json('total'));
    }

    public function test_can_get_cash_closure_orders(): void
    {
        $client = \App\Models\Client::create([
            'identification' => '28111222',
            'identification_type' => 'V-',
            'name' => 'Cliente',
            'last_name' => 'Prueba',
            'phone' => '04147778899',
            'address' => 'Dirección',
            'is_spe' => false,
        ]);

        $order = Order::create([
            'client_id' => $client->id,
            'seller_id' => $this->user->id,
            'cash_closing_id' => $this->openCash->id,
            'status' => Order::COMPLETED,
            'total_amount' => 25.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'total_amount_usd' => 25.00,
            'payment_methods' => json_encode([['method' => 'USD_CASH', 'amount' => 25.00]]),
        ]);

        $response = $this->getJson("/api/finances/cash-closure/orders?cash_id={$this->openCash->id}");

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json());
    }
}
