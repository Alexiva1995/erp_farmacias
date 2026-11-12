<?php

declare(strict_types=1);

namespace Tests\Feature\Bi;

use App\Models\Laboratory;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkuMarginAndDiscountReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Laboratory $laboratory;

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

        $this->laboratory = Laboratory::create(['name' => 'Laboratorios Megalabs']);
    }

    public function test_can_generate_sku_margin_report(): void
    {
        $product = Product::create([
            'name' => 'Vitamina D3 2000 UI',
            'laboratory_id' => $this->laboratory->id,
            'unit_cost' => 4.00,
            'sale_price' => 7.00,
            'stock' => 60,
        ]);

        $today = Carbon::now()->format('Y-m-d');

        $order = Order::create([
            'order_date' => $today,
            'status' => Order::COMPLETED,
            'total_amount' => 70.00,
            'total_amount_usd' => 70.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_type' => 'product',
            'quantity' => 10,
            'price' => 7.00,
            'unit_cost' => 4.00,
            'unit_price_usd' => 7.00,
        ]);

        $response = $this->getJson("/api/bi/sku-margin?start_date={$today}&end_date={$today}");

        $response->assertOk();
        $this->assertIsArray($response->json());
    }

    public function test_can_get_discount_dashboard_and_audit(): void
    {
        $product = Product::create([
            'name' => 'Omeprazol 40mg',
            'laboratory_id' => $this->laboratory->id,
            'unit_cost' => 2.00,
            'sale_price' => 5.00,
            'stock' => 100,
        ]);

        $today = Carbon::now()->format('Y-m-d');

        $order = Order::create([
            'order_date' => $today,
            'status' => Order::COMPLETED,
            'total_amount' => 45.00,
            'total_amount_usd' => 45.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'seller_id' => $this->user->id,
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_type' => 'product',
            'quantity' => 10,
            'price' => 4.50,
            'price_before_discount' => 5.00,
            'discount_percentage' => 10.00,
            'discount_type' => 'manual',
            'unit_cost' => 2.00,
            'unit_price_usd' => 4.50,
        ]);

        // 1. Dashboard de descuentos
        $dashboardResponse = $this->getJson("/api/bi/discounts/dashboard?start_date={$today}&end_date={$today}");
        $dashboardResponse->assertOk()
            ->assertJsonStructure(['kpis', 'distribution', 'highlights', 'rankings']);

        // 2. Auditoría de descuentos
        $auditResponse = $this->getJson("/api/bi/discounts/audit?start_date={$today}&end_date={$today}");
        $auditResponse->assertOk();
    }
}
