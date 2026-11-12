<?php

namespace Tests\Feature\Restaurant;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModifierGroupManagementTest extends TestCase
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

    public function test_can_list_and_create_modifier_group(): void
    {
        $this->getJson('/api/modifier-groups')
            ->assertOk();

        $this->postJson('/api/modifier-groups', [
            'name' => 'Extras de Bebida',
            'min_selections' => 0,
            'max_selections' => 3,
            'is_required' => false,
        ])->assertSuccessful();
    }
}
