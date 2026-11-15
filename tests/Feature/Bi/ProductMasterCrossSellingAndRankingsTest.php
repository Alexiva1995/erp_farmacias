<?php

namespace Tests\Feature\Bi;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductMasterCrossSellingAndRankingsTest extends TestCase
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

    public function test_can_fetch_product_master_cross_selling_and_rankings(): void
    {
        $this->getJson('/api/bi/products/cross-selling')
            ->assertOk();

        $this->getJson('/api/bi/products/rankings?sort_by=total_revenue')
            ->assertOk();
    }
}
