<?php

namespace Tests\Feature\Configuration;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResourceLookupTest extends TestCase
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

    public function test_can_fetch_public_and_catalog_resources(): void
    {
        $this->getJson('/api/public/exchange-rates')
            ->assertOk();

        $this->getJson('/api/laboratories')
            ->assertOk();

        $this->getJson('/api/origins')
            ->assertOk();

        $this->getJson('/api/categories')
            ->assertOk();

        $this->getJson('/api/suppliers')
            ->assertOk();
    }
}
