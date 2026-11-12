<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\ExchangeRate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ExchangeRateManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['id' => 1], [
            'name' => 'Admin',
            'guard_name' => 'web',
            'menu_permissions' => ['*'],
        ]);

        $this->user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($this->user, 'sanctum');
    }

    public function test_can_list_all_latest_exchange_rates(): void
    {
        ExchangeRate::create([
            'currency_code' => 'BS',
            'rate' => 45.5000,
            'source' => 'BCV',
        ]);

        ExchangeRate::create([
            'currency_code' => 'BS',
            'rate' => 46.2000,
            'source' => 'BCV',
        ]);

        ExchangeRate::create([
            'currency_code' => 'COP',
            'rate' => 4200.0000,
            'source' => 'CUCUTA',
        ]);

        $response = $this->getJson('/api/finances/exchange-rates');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $bsRate = collect($data)->firstWhere('currency_code', 'BS');
        $this->assertNotNull($bsRate);
        $this->assertEquals(46.2000, (float) $bsRate['rate']);

        $copRate = collect($data)->firstWhere('currency_code', 'COP');
        $this->assertNotNull($copRate);
        $this->assertEquals(4200.0000, (float) $copRate['rate']);
    }

    public function test_can_store_and_update_exchange_rate(): void
    {
        $payload = [
            'currency_code' => 'BS',
            'rate' => 48.7500,
            'source' => 'BCV OFICIAL',
        ];

        $response = $this->postJson('/api/finances/exchange-rates/store', $payload);

        $response->assertOk();

        $this->assertDatabaseHas('exchange_rates', [
            'currency_code' => 'BS',
            'rate' => 48.7500,
            'source' => 'BCV OFICIAL',
        ]);
    }

    public function test_validates_cop_minimum_threshold(): void
    {
        // En COP la tasa debe ser en pesos por dólar (mínimo 100)
        $payload = [
            'currency_code' => 'COP',
            'rate' => 0.0002, // Invalido
        ];

        $response = $this->postJson('/api/finances/exchange-rates/store', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_can_consult_specific_currency_rates(): void
    {
        ExchangeRate::create([
            'currency_code' => 'BS',
            'rate' => 49.1000,
            'source' => 'BCV',
        ]);

        ExchangeRate::create([
            'currency_code' => 'BINANCE',
            'rate' => 52.3000,
            'source' => 'P2P',
        ]);

        ExchangeRate::create([
            'currency_code' => 'EUR',
            'rate' => 1.0850,
            'source' => 'ECB',
        ]);

        ExchangeRate::create([
            'currency_code' => 'COP',
            'rate' => 4350.0000,
            'source' => 'EFECTIVO',
        ]);

        // Consultar BCV
        $bcvResponse = $this->getJson('/api/finances/exchange-rates/consultOneBCV');
        $bcvResponse->assertOk()
            ->assertJsonPath('currency_code', 'BS');
        $this->assertEquals(49.1, (float) $bcvResponse->json('rate'));

        // Consultar Binance
        $binanceResponse = $this->getJson('/api/finances/exchange-rates/consultOneBINANCE');
        $binanceResponse->assertOk()
            ->assertJsonPath('currency_code', 'BINANCE');
        $this->assertEquals(52.3, (float) $binanceResponse->json('rate'));

        // Consultar Euro
        $eurResponse = $this->getJson('/api/finances/exchange-rates/consultOneEUR');
        $eurResponse->assertOk()
            ->assertJsonPath('currency_code', 'EUR');
        $this->assertEquals(1.085, (float) $eurResponse->json('rate'));

        // Consultar COP
        $copResponse = $this->getJson('/api/finances/exchange-rates/consultOneCOP');
        $copResponse->assertOk()
            ->assertJsonPath('currency_code', 'COP');
        $this->assertEquals(4350.0, (float) $copResponse->json('rate'));
    }
}
