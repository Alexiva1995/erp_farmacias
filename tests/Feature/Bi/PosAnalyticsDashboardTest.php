<?php

declare(strict_types=1);

namespace Tests\Feature\Bi;

use App\Models\CashClosing;
use App\Models\Client;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PosAnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $seller;
    private Client $client;

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

        $this->admin = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->seller = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin);

        $this->client = Client::create([
            'identification' => '24111222',
            'identification_type' => Client::IDENTIFICATION_TYPE_VENEZOLANO,
            'name' => 'Gabriel',
            'last_name' => 'Mendoza',
            'phone' => '04128887766',
            'address' => 'Urb. El Centro',
            'is_spe' => false,
        ]);

        $cash = CashClosing::create([
            'seller_id' => $this->seller->id,
            'status' => CashClosing::OPEN,
            'closing_date' => now(),
        ]);

        $order = Order::create([
            'client_id' => $this->client->id,
            'seller_id' => $this->seller->id,
            'cash_closing_id' => $cash->id,
            'status' => Order::COMPLETED,
            'total_amount' => 50.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'total_amount_usd' => 50.00,
            'order_date' => now(),
            'payment_methods' => json_encode([['method' => 'USD_CASH', 'amount' => 50.00]]),
        ]);

        $product = Product::create([
            'name' => 'Amoxicilina 500mg',
            'unit_cost' => 2.00,
            'sale_price' => 5.00,
            'iva' => 0,
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_type' => 'normal',
            'quantity' => 10,
            'price' => 5.00,
            'unit_cost' => 2.00,
            'total_cost' => 20.00,
        ]);
    }

    public function test_can_get_pos_analytics_dashboard(): void
    {
        $response = $this->getJson('/api/bi/pos/dashboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'kpis' => [
                    'completed_sales',
                    'avg_ticket',
                    'total_revenue',
                ],
                'charts',
                'segmentation',
            ],
        ]);
    }

    public function test_can_filter_pos_analytics_dashboard_by_date_range_and_seller(): void
    {
        $startDate = now()->subDays(7)->toDateString();
        $endDate = now()->toDateString();

        $response = $this->getJson("/api/bi/pos/dashboard?start_date={$startDate}&end_date={$endDate}&seller_id={$this->seller->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'kpis' => [
                    'completed_sales',
                    'avg_ticket',
                    'total_revenue',
                ],
                'charts',
                'segmentation',
            ],
        ]);
    }
}
