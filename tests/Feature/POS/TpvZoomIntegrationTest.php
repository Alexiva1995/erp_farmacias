<?php

declare(strict_types=1);

namespace Tests\Feature\POS;

use App\Models\CashClosing;
use App\Models\Client;
use App\Models\ExchangeRate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TpvZoomIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private Client $client;
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
            'identification' => '23456789',
            'identification_type' => Client::IDENTIFICATION_TYPE_VENEZOLANO,
            'name' => 'Marcos',
            'last_name' => 'Perez',
            'phone' => '04149876543',
            'address' => 'Av. Principal Local 1',
            'is_spe' => false,
        ]);

        $this->openCash = CashClosing::create([
            'seller_id' => $this->seller->id,
            'status' => CashClosing::OPEN,
            'closing_date' => now(),
        ]);
    }

    public function test_can_get_or_create_zoom_product(): void
    {
        $response = $this->getJson('/api/tpv/zoom/product?is_taxable=1');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $this->assertNotNull($response->json('data.product'));
    }

    public function test_can_add_zoom_item_to_active_tpv_order(): void
    {
        $payload = [
            'description' => 'Envío Zoom Caracas - Valencia',
            'amount_net' => 15.00,
            'currency' => 'USD',
            'client_id' => $this->client->id,
            'is_taxable' => true,
        ];

        $response = $this->postJson('/api/tpv/zoom/add-to-order', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Servicio Zoom agregado a la orden exitosamente.',
        ]);

        $this->assertDatabaseHas('orders', [
            'seller_id' => $this->seller->id,
            'status' => 'Pending',
        ]);

        $this->assertDatabaseHas('order_details', [
            'price' => 15.00,
            'product_type' => 'product',
        ]);
    }

    public function test_validates_required_fields_when_adding_zoom_item(): void
    {
        $response = $this->postJson('/api/tpv/zoom/add-to-order', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['description', 'amount_net']);
    }
}
