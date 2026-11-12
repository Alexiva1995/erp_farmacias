<?php

namespace Tests\Feature\Wholesale;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortfolioManagementTest extends TestCase
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

    public function test_can_fetch_portfolio_metrics_and_commissions(): void
    {
        Client::create([
            'name' => 'Distribuidora Central C.A.',
            'identification' => 'J-40123456-8',
            'sales_rep_id' => $this->admin->id,
        ]);

        $this->getJson('/api/wholesale/portfolio')
            ->assertOk();

        $this->getJson('/api/wholesale/portfolio/metrics')
            ->assertOk();

        $today = now()->toDateString();

        $this->getJson("/api/wholesale/portfolio/commissions?sales_rep_id={$this->admin->id}&start_date={$today}&end_date={$today}")
            ->assertOk();
    }
}
