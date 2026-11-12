<?php

namespace Tests\Feature\Inventory;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupManagementTest extends TestCase
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

    public function test_can_list_create_and_delete_group(): void
    {
        $this->getJson('/api/groups')
            ->assertOk();

        $this->getJson('/api/groups/consult-all')
            ->assertOk();

        $createResponse = $this->postJson('/api/groups', [
            'name' => 'Analgésicos',
            'description' => 'Grupo de analgésicos',
        ]);

        $createResponse->assertSuccessful();

        $groupId = $createResponse->json('data.id') ?? $createResponse->json('id');

        if ($groupId) {
            $this->deleteJson("/api/groups/{$groupId}")
                ->assertSuccessful();
        }
    }
}
