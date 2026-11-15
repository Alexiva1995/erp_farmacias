<?php

namespace Tests\Feature\Finances;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionListingTest extends TestCase
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

    public function test_can_fetch_transactions_stats_wallets_and_cash_status(): void
    {
        $this->getJson('/api/transactions')
            ->assertOk();

        $this->getJson('/api/transactions/stats')
            ->assertOk();

        $this->getJson('/api/transactions/wallets')
            ->assertOk();

        $this->getJson('/api/transactions/income-summary')
            ->assertOk();

        $this->getJson('/api/transactions/cash-status')
            ->assertOk();
    }
}
