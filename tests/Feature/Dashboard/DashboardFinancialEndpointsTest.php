<?php

namespace Tests\Feature\Dashboard;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardFinancialEndpointsTest extends TestCase
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

    public function test_can_fetch_dashboard_financial_endpoints(): void
    {
        $this->getJson('/api/dashboard/total-income')
            ->assertOk();

        $this->getJson('/api/dashboard/deductible-expenses')
            ->assertOk();

        $this->getJson('/api/dashboard/non-deductible-expenses')
            ->assertOk();

        $this->getJson('/api/dashboard/revenue-report')
            ->assertOk();

        $this->getJson('/api/dashboard/fiscal-retentions')
            ->assertOk();
    }
}
