<?php

namespace Tests\Feature\Bi;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

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

        $this->admin = User::factory()->create([
            'role_id' => $role->id,
        ]);

        Sanctum::actingAs($this->admin);
    }

    public function test_can_fetch_dashboard_stats_and_financial_summaries(): void
    {
        $today = now()->toDateString();

        Order::create([
            'order_date' => $today,
            'status' => Order::COMPLETED,
            'total_amount' => 100.00,
            'total_amount_usd' => 100.00,
            'money_returns' => 0.00,
            'currency' => 'USD',
            'seller_id' => $this->admin->id,
        ]);

        $this->getJson("/api/dashboard/stats?start_date={$today}&end_date={$today}")
            ->assertOk();

        $this->getJson("/api/dashboard/total-income?start_date={$today}&end_date={$today}")
            ->assertOk();

        $this->getJson("/api/dashboard/profit?start_date={$today}&end_date={$today}")
            ->assertOk();

        $this->getJson("/api/dashboard/client-stats?start_date={$today}&end_date={$today}")
            ->assertOk();
    }
}
