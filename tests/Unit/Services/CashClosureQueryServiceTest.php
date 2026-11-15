<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\CashClosing;
use App\Models\Client;
use App\Models\DailyCashClosure;
use App\Models\Employee;
use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\CashClosure\CashClosureQueryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CashClosureQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private CashClosureQueryService $service;
    private User $admin;
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CashClosureQueryService();

        $adminRole = Role::firstOrCreate(['id' => 1], ['name' => 'Admin', 'guard_name' => 'web', 'menu_permissions' => ['*']]);
        $sellerRole = Role::firstOrCreate(['id' => 2], ['name' => 'Vendedor', 'guard_name' => 'web', 'menu_permissions' => ['pos']]);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true, 'username' => 'admin_qa']);
        $this->seller = User::factory()->create(['role_id' => $sellerRole->id, 'is_active' => true, 'username' => 'seller_qa']);

        ExchangeRate::create(['currency_code' => 'BS', 'rate' => 40.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'COP', 'rate' => 4000.0, 'source' => 'test']);
        ExchangeRate::create(['currency_code' => 'EUR', 'rate' => 42.0, 'source' => 'test']);
    }

    public function test_filtered_queries_for_closures_and_open_cash_orders(): void
    {
        // Sin usuario autenticado debe retornar consulta vacía
        $this->assertCount(0, $this->service->getFilteredQuery(new Request())->get());

        $this->actingAs($this->seller);

        // Sin cierre abierto debe lanzar ModelNotFoundException
        try {
            $this->service->getFilteredQueryOrder(new Request());
            $this->fail('Se esperaba ModelNotFoundException');
        } catch (ModelNotFoundException $e) {
            $this->assertStringContainsString('cierre de caja abierto', $e->getMessage());
        }

        CashClosing::create([
            'seller_id' => $this->seller->id,
            'status' => CashClosing::CLOSED,
            'closing_date' => now()->subDay(),
            'total_sales' => 100,
        ]);

        $openCash = CashClosing::create([
            'seller_id' => $this->seller->id,
            'status' => CashClosing::OPEN,
            'closing_date' => now(),
            'total_sales' => 250,
            'total_usd' => 250,
        ]);

        $client = Client::create([
            'identification' => 'V-20111222',
            'identification_type' => Client::IDENTIFICATION_TYPE_VENEZOLANO,
            'name' => 'Carlos',
            'last_name' => 'Mendoza',
        ]);

        Order::create([
            'seller_id' => $this->seller->id,
            'client_id' => $client->id,
            'cash_closing_id' => $openCash->id,
            'total_amount' => 120.00,
            'total_amount_usd' => 120.00,
            'money_returns' => 0,
            'status' => Order::COMPLETED,
            'order_date' => now(),
            'currency' => 'USD',
        ]);

        $this->assertCount(1, $this->service->getFilteredQuery(new Request(['sortBy' => 'id', 'orderBy' => 'asc']))->get());
        $this->assertCount(1, $this->service->getFilteredQuery(new Request(['sortBy' => 'date', 'orderBy' => 'desc']))->get());

        foreach (['id', 'total_amount', 'currency', 'date', 'client_full_name', 'identification', 'other'] as $sort) {
            $orders = $this->service->getFilteredQueryOrder(new Request(['sortBy' => $sort, 'orderBy' => 'asc']))->get();
            $this->assertCount(1, $orders);
        }
    }

    public function test_daily_monthly_seller_cash_and_sellers_with_closures(): void
    {
        Employee::create([
            'user_id' => $this->seller->id,
            'name' => 'Pedro',
            'last_name' => 'Vendedor',
            'identification' => 'V-19000111',
            'is_active' => true,
        ]);

        $daily = DailyCashClosure::create([
            'total_sales' => 500,
            'total_usd' => 300,
            'total_cop' => 400000,
            'total_bs' => 4000,
            'bs_cash' => 1000,
            'bs_mobile' => 1500,
            'bs_transfer' => 500,
            'bs_card_debito' => 500,
            'bs_card_credit' => 500,
            'usd_cash' => 300,
            'cop_cash' => 400000,
            'usd_delivered' => 300,
            'cop_delivered' => 400000,
            'bs_delivered' => 4000,
            'total_credits' => 50,
            'created_at' => '2026-04-10 18:00:00',
        ]);

        CashClosing::create([
            'seller_id' => $this->seller->id,
            'daily_closure_id' => $daily->id,
            'status' => CashClosing::CLOSED,
            'closing_date' => now(),
            'total_sales' => 250,
            'total_usd' => 250,
        ]);

        // Rol 2 aplica límite de 10 últimos cierres diarios
        $this->actingAs($this->seller);
        foreach (['id', 'date', 'total_usd', 'total_cop', 'total_bs', 'usd_delivered', 'cop_delivered', 'bs_mobile', 'bs_card', 'other'] as $sortDaily) {
            $res = $this->service->getFilteredQueryDaily(new Request(['sortBy' => $sortDaily, 'orderBy' => 'desc']))->get();
            $this->assertCount(1, $res);
        }

        // Mensual (usa YEAR/MONTH/GROUP_CONCAT compatibles vía TestCase)
        $this->actingAs($this->admin);
        foreach ([null, 'closing_date', 'amount_usd', 'amount_cop', 'amount_bs', 'daily_average'] as $sortMonthly) {
            $monthly = $this->service->getFilteredQueryMonthly(new Request(['sortBy' => $sortMonthly, 'orderBy' => 'desc']));
            $this->assertCount(1, $monthly);
            $this->assertEquals(1, $monthly->first()->days_closed);
        }

        // Seller cash con filtros
        foreach (['total_sales', 'total_usd', 'total_cop', 'total_bs', 'status', null] as $sortSeller) {
            $sellerClosings = $this->service->getFilteredQuerySellerCash(new Request([
                'q' => $this->seller->id,
                'start_date' => now()->subDay()->toDateString(),
                'end_date' => now()->addDay()->toDateString(),
                'sortBy' => $sortSeller,
                'orderBy' => 'desc',
            ]))->get();
            $this->assertCount(1, $sellerClosings);
        }

        // Filtros de fechas individuales (solo start_date o solo end_date)
        $this->assertCount(1, $this->service->getFilteredQuerySellerCash(new Request(['start_date' => now()->subDay()->toDateString()]))->get());
        $this->assertCount(1, $this->service->getFilteredQuerySellerCash(new Request(['end_date' => now()->addDay()->toDateString()]))->get());

        $sellers = $this->service->getSellersWithClosures();
        $this->assertCount(1, $sellers);
        $this->assertSame($this->seller->id, $sellers->first()->id);
    }
}
