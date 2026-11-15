<?php

namespace Tests\Feature\CashClosure;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashClosureListingTest extends TestCase
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

    public function test_can_fetch_cash_closure_history_summary_and_daily_cash(): void
    {
        $this->getJson('/api/cash-closure')
            ->assertOk();

        $this->getJson('/api/cash-closure/closingHistory')
            ->assertOk();

        $this->getJson('/api/cash-closure/sales/summary')
            ->assertOk();

        $this->getJson('/api/cash-closure/dailyCash')
            ->assertOk();
    }
}
