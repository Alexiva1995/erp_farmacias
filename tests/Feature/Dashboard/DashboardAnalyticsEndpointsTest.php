<?php

namespace Tests\Feature\Dashboard;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardAnalyticsEndpointsTest extends TestCase
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

    public function test_can_fetch_dashboard_stats_popular_products_and_client_stats(): void
    {
        $this->getJson('/api/dashboard/stats')
            ->assertOk();

        $this->getJson('/api/dashboard/popular-products')
            ->assertOk();

        $this->getJson('/api/dashboard/client-stats')
            ->assertOk();
    }
}
