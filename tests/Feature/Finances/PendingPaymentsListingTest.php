<?php

namespace Tests\Feature\Finances;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PendingPaymentsListingTest extends TestCase
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

    public function test_can_fetch_pending_payments_statistics_suppliers_and_history(): void
    {
        $this->getJson('/api/pending-payments')
            ->assertOk();

        $this->getJson('/api/pending-payments/statistics')
            ->assertOk();

        $this->getJson('/api/pending-payments/suppliers')
            ->assertOk();

        $this->getJson('/api/pending-payments/expenses-history')
            ->assertOk();
    }
}
