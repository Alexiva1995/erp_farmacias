<?php

namespace Tests\Feature\POS;

use App\Models\User;
use App\Models\CashClosing;
use App\Models\ExchangeRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CashRegisterClosureTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private CashClosing $openCash;

    protected function setUp(): void
    {
        parent::setUp();

        // Limpiar cache de tasas de cambio
        Cache::forget('resources.all_exchange_rates');

        // Sembrar tasas de cambio necesarias en BD
        ExchangeRate::create(['currency_code' => 'USD', 'rate' => 1.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'BS', 'rate' => 36.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'COP', 'rate' => 4000.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'EUR', 'rate' => 39.0, 'source' => 'test']);

        // Crear un usuario vendedor/cajero
        $this->seller = User::create([
            'username' => 'cajero2',
            'email' => 'cajero2@farmacia.com',
            'password_hash' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Crear una caja abierta para el vendedor
        $this->openCash = CashClosing::create([
            'seller_id' => $this->seller->id,
            'status' => CashClosing::OPEN,
            'closing_date' => now(),
            'total_usd' => 100.0,
            'usd_cash' => 100.0,
            'total_cop' => 400000.0,
            'cop_cash' => 400000.0,
        ]);
    }

    /**
     * Valida que se pueda consultar el estado de la caja registradora abierta.
     */
    public function test_get_current_open_cash_closure(): void
    {
        $response = $this->actingAs($this->seller, 'sanctum')
            ->getJson('/api/finances/cash-closure');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertEquals($this->openCash->id, $data['id']);
        $this->assertEquals('open', $data['status']);
        $this->assertEquals($this->seller->id, $data['seller_id']);
    }

    /**
     * Valida el flujo exitoso de cierre y cuadre de caja consolidando ingresos.
     */
    public function test_close_cash_register_successfully(): void
    {
        $response = $this->actingAs($this->seller, 'sanctum')
            ->postJson('/api/finances/cash-closure/close', [
                'id' => $this->openCash->id,
                'total_cop' => 400000.0,
                'sobrante_en_peso' => 0,
                'entregar_efectivo_cop' => 400000.0,
                'ticket_html' => '<div>Ticket de cierre de prueba</div>',
                'history_html' => '<div>Historial de prueba</div>',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Caja cerrada exitosamente.',
            ]);

        // Validar que la caja original esté cerrada
        $this->openCash->refresh();
        $this->assertEquals('closed', $this->openCash->status);
        $this->assertNotNull($this->openCash->closing_date);
    }
}
