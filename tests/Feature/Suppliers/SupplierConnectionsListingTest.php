<?php

namespace Tests\Feature\Suppliers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierConnectionsListingTest extends TestCase
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

    public function test_can_fetch_supplier_connections_and_statuses(): void
    {
        $this->getJson('/api/suppliers/connections')
            ->assertOk();

        $this->getJson('/api/suppliers/supplier-connection-statuses')
            ->assertOk();
    }

    public function test_supplier_connections_returns_resolved_logo_url(): void
    {
        config()->set('catalog.master_asset_url', '');
        config()->set('catalog.master_url', 'https://farmaciabs.com/api/v1/master-catalog');

        \App\Models\Supplier::create([
            'id' => 2001,
            'name' => 'DROGUERIA CONEXION TEST',
            'rif' => 'J-12345678-9',
            'logo_url' => '/images/suppliers/test_conn.png',
            'type' => 'drogueria',
            'dispatch_days' => [],
            'order_days' => [],
            'is_active' => true,
        ]);

        $this->getJson('/api/suppliers/connections')
            ->assertOk()
            ->assertJsonPath('data.0.logo_url', 'https://farmaciabs.com/images/suppliers/test_conn.png')
            ->assertJsonPath('data.0.photo_url', 'https://farmaciabs.com/images/suppliers/test_conn.png');
    }
}
