<?php

namespace Tests\Feature\Bi;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductMasterReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'Admin',
                'guard_name' => 'web',
                'menu_permissions' => ['*'],
            ]
        );

        $this->user = User::factory()->create([
            'role_id' => $role->id,
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_can_fetch_product_master_dashboard_trends_cross_selling_and_rankings(): void
    {
        $productA = Product::create([
            'name' => 'Acetaminofen 500mg',
            'code' => 'ACE-500',
            'price' => 3.50,
            'cost' => 1.80,
            'stock' => 100,
            'status' => 1,
        ]);

        $productB = Product::create([
            'name' => 'Vitamina C 1000mg',
            'code' => 'VIT-1000',
            'price' => 5.00,
            'cost' => 2.50,
            'stock' => 60,
            'status' => 1,
        ]);

        $today = now()->toDateString();

        $order = Order::create([
            'order_date' => $today,
            'status' => Order::COMPLETED,
            'total_amount' => 8.50,
            'total_amount_usd' => 8.50,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'seller_id' => $this->user->id,
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $productA->id,
            'product_type' => 'product',
            'quantity' => 1,
            'price' => 3.50,
            'unit_cost' => 1.80,
            'unit_price_usd' => 3.50,
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $productB->id,
            'product_type' => 'product',
            'quantity' => 1,
            'price' => 5.00,
            'unit_cost' => 2.50,
            'unit_price_usd' => 5.00,
        ]);

        $this->getJson("/api/bi/products/dashboard?start_date={$today}&end_date={$today}")
            ->assertOk();

        $this->getJson("/api/bi/products/trends?start_date={$today}&end_date={$today}")
            ->assertOk();

        $this->getJson("/api/bi/products/cross-selling?start_date={$today}&end_date={$today}")
            ->assertOk();

        $this->getJson("/api/bi/products/rankings?start_date={$today}&end_date={$today}&sort_by=total_revenue")
            ->assertOk();
    }
}
