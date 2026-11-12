<?php

namespace Tests\Feature\POS;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CasheaCreditManagementTest extends TestCase
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

    public function test_can_fetch_cashea_credits_kpis_and_schedule(): void
    {
        $this->getJson('/api/credits/cashea')
            ->assertOk();

        $this->getJson('/api/credits/cashea/kpis')
            ->assertOk();

        $this->getJson('/api/credits/cashea/schedule')
            ->assertOk();
    }
}
