<?php

namespace Tests\Feature\Suppliers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
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

    public function test_can_fetch_supplier_stats_lookup_and_disabled(): void
    {
        $this->getJson('/api/suppliers/stats')
            ->assertOk();

        $this->getJson('/api/suppliers/lookup?rif=J-30123456-7')
            ->assertOk();

        $this->getJson('/api/suppliers/disabled')
            ->assertOk();
    }

    public function test_resolves_relative_supplier_logo_url_to_master_origin_when_missing_locally(): void
    {
        config()->set('catalog.master_asset_url', '');
        config()->set('catalog.master_url', 'https://farmaciabs.com/api/v1/master-catalog');

        $this->assertNull(\App\Models\Supplier::resolveLogoUrl(null));
        $this->assertNull(\App\Models\Supplier::resolveLogoUrl('   '));
        $this->assertSame(
            'https://cdn.example.com/logo.png',
            \App\Models\Supplier::resolveLogoUrl('https://cdn.example.com/logo.png')
        );
        $this->assertSame(
            'https://farmaciabs.com/images/suppliers/non_existent_logo_test.png',
            \App\Models\Supplier::resolveLogoUrl('/images/suppliers/non_existent_logo_test.png')
        );

        config()->set('catalog.master_asset_url', 'https://master-assets.tovaerp.com');
        $this->assertSame(
            'https://master-assets.tovaerp.com/images/suppliers/non_existent_logo_test.png',
            \App\Models\Supplier::resolveLogoUrl('images/suppliers/non_existent_logo_test.png')
        );

        $supplier = new \App\Models\Supplier([
            'id' => 999,
            'name' => 'Drogueria Test',
            'logo_url' => '/images/suppliers/non_existent_logo_test.png',
        ]);
        $resource = (new \App\Http\Resources\SupplierResource($supplier))->resolve();
        $this->assertSame(
            'https://master-assets.tovaerp.com/images/suppliers/non_existent_logo_test.png',
            $resource['logo_url']
        );
    }

    public function test_propagate_merge_requires_master_context_and_validates_target(): void
    {
        $target = \App\Models\Supplier::create([
            'id' => 1032,
            'name' => 'INTERCONTINENTAL',
            'rif' => 'J-50159019-2',
            'dispatch_days' => [],
            'order_days' => [],
            'is_active' => true,
        ]);

        $this->postJson('/api/suppliers/propagate-merge', [
            'target_supplier_id' => $target->id,
            'source_supplier_id' => 1043,
        ])->assertStatus(403);

        $this->postJson('/api/suppliers/propagate-merge?is_master=1', [
            'target_supplier_id' => $target->id,
            'source_supplier_id' => 1043,
        ])->assertOk()
            ->assertJsonStructure(['message', 'data' => ['tenants_checked', 'tenants_merged', 'errors']]);
    }

    public function test_can_save_and_fetch_supplier_scraper_independently_of_connections(): void
    {
        $supplier = \App\Models\Supplier::create([
            'id' => 1014,
            'name' => 'DRONENA',
            'rif' => 'J-08518977-7',
            'dispatch_days' => [],
            'order_days' => [],
            'is_active' => true,
        ]);

        $this->postJson("/api/supplier-scrapers/{$supplier->id}/dronena", [
            'portal_url' => 'https://www.dronena.com/NuevaExperiencia/',
            'username' => 'bot_user',
            'password' => 'secret123',
        ])->assertOk()
            ->assertJsonPath('data.scraper_key', 'dronena')
            ->assertJsonPath('data.username', 'bot_user')
            ->assertJsonPath('data.has_password', true);

        $this->getJson("/api/supplier-scrapers/{$supplier->id}/dronena")
            ->assertOk()
            ->assertJsonPath('data.scraper_key', 'dronena')
            ->assertJsonPath('data.username', 'bot_user')
            ->assertJsonPath('data.has_password', true);

        $this->assertDatabaseCount('supplier_connections', 0);
        $this->assertDatabaseHas('supplier_scrapers', [
            'supplier_id' => $supplier->id,
            'scraper_key' => 'dronena',
            'username' => 'bot_user',
        ]);
    }
}
