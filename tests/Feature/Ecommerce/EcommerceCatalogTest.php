<?php

namespace Tests\Feature\Ecommerce;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EcommerceCatalogTest extends TestCase
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

    public function test_can_fetch_public_ecommerce_catalog_and_admin_orders(): void
    {
        $this->getJson('/api/public/ecommerce/products')
            ->assertOk();

        $this->getJson('/api/public/ecommerce/categories')
            ->assertOk();

        $this->getJson('/api/ecommerce/admin/orders')
            ->assertOk();
    }
}
